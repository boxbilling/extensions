<?php
/**
 * Partner program
 */
class Box_Mod_Partner_Api_Client extends Api_Abstract
{
    private $service = null;
    
    protected function init()
    {
        Box_Db::getRb();
        $this->service = new Box_Mod_Partner_Service();
        $this->client = R::findOne('client', 'id = :id', array('id'=>$this->_identity->id));
        
        /*
        $mod_c = $this->_getConfig();
        if(!isset($mod_c['enabled']) && !$mod_c['enabled']) {
            throw new Box_Exception('Partners program is temporary disabled. Check back soon.');
        }
        */
    }
    
    /**
     * Sign up for partners program
     * 
     * @return bool
     */
    public function signup($data)
    {
        if($this->is_partner()) {
            return true;
        }
        
        if(!isset($data['agree']) || !$data['agree']) {
            throw new Box_Exception('You must agree with terms and conditions of partners program.', null, 8801);
        }
        
        $mod_c = $this->_getConfig();
        if(isset($mod_c['required_balance']) && $mod_c['required_balance']) {
            $client = $this->getApiAdmin()->client_get(array('id'=>$this->client->id));
            if($client['balance'] < $mod_c['required_balance']) {
                throw new Box_Exception('To become a partner you will have to deposit $:amount. This $:amount will be an Advance and will be set off against the licenses you Purchase / Renew. So when you pay this $:amount a Balance will be shown in your account.', array(':amount'=>$mod_c['required_balance']), 8802);
            }
        }
        
        // Check if product must be ordered before activating partners program
        if(isset($mod_c['pid']) && $mod_c['pid']) {
            $pid = $mod_c['pid'];
            $params = array('cid'=>$this->client->id, 'pid'=>$pid, 'status'=>'active');
            $has = R::findOne('client_order', 'client_id = :cid AND product_id = :pid AND status = :status', $params);
            $title = R::getCell('SELECT title FROM product WHERE id = :id', array('id'=>$pid));
            if(!$has) {
                throw new Box_Exception('To become a partner it is required you to order :product', array(':product'=>$title), 8803);
            }
        }
        
        $this->service->createPartner($this->client);
        
        $this->getApiAdmin()->hook_call(array('event'=>'onAfterClientBecomePartner', 'params'=>array('client_id'=>$this->client->id)));
        $this->_log('Client %s signed up for partners program', $this->client->id);
        return true;
    }
    
    /**
     * Get paginated list of orders
     * 
     * @return array - paginated list
     */
    public function orders($data)
    {
        $ids = $this->service->getPartnerOrdersIds($this->client->id);
		if(empty($ids)) {
			return array();
		}
        $data['ids'] = $ids;
        return $this->getApiAdmin()->order_get_list($data);
    }
    
    /**
     * Create new license order with partners pricing
     * 
     * @return int - order id
     */
    public function order_create()
    {
        $this->_checkPartner();
        
        $mod_c = $this->_getConfig();
        if(isset($mod_c['orders_limit']) && $mod_c['orders_limit']) {
            $sql = "SELECT COUNT(id) FROM client_order WHERE client_id = :cid AND DATE_FORMAT(created_at, '%Y-%m-%d') = :date GROUP BY client_id";
            $count = R::getCell($sql, array('cid'=>$this->client->id, ':date'=>date('Y-m-d')));
            if($count >= $mod_c['orders_limit']) {
                throw new Box_Exception('Daily orders limit reached. Order today :today out of :limit', array(':today'=>$count, ':limit'=>$mod_c['orders_limit']), 8804);
            }
        }
        
        $partner = $this->service->getPartner($this->client);
        $price = $this->service->getPartnerPrice($this->client);
        
        if(isset($mod_c['check_balance']) && $mod_c['check_balance']) {
            $client = $this->getApiAdmin()->client_get(array('id'=>$this->client->id));
            if($client['balance'] < $price) {
                throw new Box_Exception('Not enough money in balance to create order. You must have at least $:price in your balance', array(':price'=>$price), 8808);
            }
        }
        
        if(!isset($mod_c['lid'])) {
            throw new Box_Exception('Partners program is temporary disabled.',null, 8806);
        }
        
        if($partner->product_id) {
            $product_id = $partner->product_id;
        } else {
            $product_id = $mod_c['lid'];
        }
        
        $odata = array(
            'client_id'         =>  $this->client->id,
            'product_id'        =>  $product_id,
            'price'             =>  $price,
            'quantity'          =>  1,
            'period'            =>  '1M',
            'activate'          =>  true,
            'invoice_option'    =>  'no-invoice',
            'config'            =>  array(
                'partner_id'    =>  $this->client->id,
            ),
        );
        
        $order_id = $this->getApiAdmin()->order_create($odata);
        
        $odata = array(
            'id'            => $order_id,
            'invoice_option'=> 'issue-invoice',
            'expires_at'    => date('Y-m-d', strtotime('+14 days')),
        );
        $this->getApiAdmin()->order_update($odata);
        
        $this->service->addOrderForPartner($this->client, $order_id);
        
        $this->getApiAdmin()->hook_call(array('event'=>'onAfterPartnerOrderCreate', 'params'=>array('client_id'=>$this->client->id, 'order_id'=>$order_id)));
        
        $this->_log('Partner created new order #%s', $order_id);
        return $order_id;
    }
    
    /**
     * Get order details
     * 
     * @param $order_id - license order id
     * @return array
     */
    public function order_get($data)
    {
        $id = $this->_getOrderId($data);
        $odata = array(
            'id'        =>  $id,
        );
        $order = $this->getApiAdmin()->order_get($odata);
        
        $result = array();
        $result['status']       = $order['status'];
        $result['expires_at']   = $order['expires_at'];
        $result['created_at']   = $order['created_at'];
        $result['updated_at']   = $order['updated_at'];
        
        $service = $this->getApiAdmin()->order_service($odata);
        if(!empty($service)) {
            $result['license_key']  = $service['license_key'];
            $result['ips']          = $service['ips'];
            $result['hosts']        = $service['hosts'];
            $result['paths']        = $service['paths'];
            $result['versions']     = $service['versions'];
        }
        
        return $result;
    }
    
    /**
     * Suspend license order
     * 
     * @param $order_id - license order id
     * @return bool
     */
    public function order_suspend($data)
    {
        $id = $this->_getOrderId($data);
        $odata = array(
            'id'        =>  $id,
            'reason'    =>  isset($data['reason']) ? $data['reason'] : NULL,
        );
        $this->getApiAdmin()->order_suspend($odata);
        $this->_log('Partner suspended order #%s', $id);
        return true;
    }
    
    /**
     * Unsuspend license order
     * 
     * @param $order_id - license order id
     * @return bool
     */
    public function order_unsuspend($data)
    {
        $id = $this->_getOrderId($data);
        $odata = array(
            'id'        =>  $id,
        );
        $this->getApiAdmin()->order_renew($odata);
        $this->_log('Partner unsuspended order #%s', $id);
        return true;
    }
    
    /**
     * Delete license order
     * 
     * @param $order_id - license order id
     * @return bool
     */
    public function order_delete($data)
    {
        $id = $this->_getOrderId($data);
        $odata = array(
            'id'        =>  $id,
        );
        $this->getApiAdmin()->order_cancel($odata);
        $this->_log('Partner canceled order #%s', $id);
        return true;
    }
    
    /**
     * Gives option for client to reset licensing details if server has changed
     * 
     * @param $order_id - license order id
     * @return bool
     */
    public function order_reset($data)
    {
        $id = $this->_getOrderId($data);
        $order = $this->getApiAdmin()->order_get(array('id'=>$id));
        if($order['status'] == 'canceled') {
            $this->getApiAdmin()->order_update(array('id'=>$id, 'status'=>'active'));
        }
        
        $odata = array(
            'order_id'        =>  $id,
        );
        $this->getApiAdmin()->servicelicense_reset($odata);
        $this->_log('Partner reset license order #%s details', $id);
        return true;
    }
    
    /**
     * Returs true if you are a partner
     * 
     * @return bool
     */
    public function is_partner()
    {
        return $this->service->isPartner($this->client);
    }
    
    /**
     * Return partners profile
     * @return array 
     */
    public function profile()
    {
        $partner = $this->service->getPartner($this->client);
        if(!is_object($partner)) {
            return array();
        }
        return $this->service->toApiArray($partner, $this->_role);
    }
    
    public function profile_update($data)
    {
        $this->_checkPartner();
        
        $partner = $this->service->getPartner($this->client);
        if(!is_object($partner)) {
            throw new Box_Exception('Partner not found');
        }
        
        if(isset($data['website'])) {
            $partner->website = $data['website'];
        }
        
        if(isset($data['logo'])) {
            $partner->logo = $data['logo'];
        }
        
        if(isset($data['public'])) {
            $partner->public = (bool)$data['public'];
            if($partner->public) {
                $this->_checkCanGoPublic($partner);
            }
        }
        
        $partner->updated_at = date('c');
        R::store($partner);
        
        $this->_log('Partner updated profile');
        return true;
    }
    
    private function _getConfig()
    {
        return $this->getApiAdmin()->extension_config_get(array('ext'=>'mod_partner'));
    }
    
    private function _checkPartner()
    {
        // check if client is active partner
        if(!$this->service->isPartner($this->client)) {
            throw new Box_Exception('You have not signed up for partners program.');
        }
        
        $partner = $this->service->getPartner($this->client);
        if($partner->status != 'active') {
            throw new Box_Exception('Your partner account is currently not active.');
        }
    }
    
    private function _checkCanGoPublic($partner)
    {
        if(empty($partner->website)) {
            throw new Box_Exception('Set your website url if you want to get listed on official partners page');
        }
        
        if(empty($partner->logo)) {
            throw new Box_Exception('Set your logo if you want to get listed on official partners page');
        }
        
        $data = $this->service->toApiArray($partner, $this->_role);
        if($data['sales'] == 0) {
            throw new Box_Exception('You must have at least one active order to get listed on official partners page');
        }
    }
    
    private function _getOrderId($data)
    {
        $this->_checkPartner();
        
        if(!isset($data['order_id'])) {
            throw new Box_Exception('Order id not passed');
        }

        $ids = $this->service->getPartnerOrdersIds($this->client->id);
        if(!in_array($data['order_id'], $ids)) {
            throw new Box_Exception('Order is not valid');
        }
        
        $sql = "SELECT id FROM client_order WHERE id = :id AND client_id = :cid";
        $id = R::getCell($sql, array('cid'=>$this->client->id, ':id'=>$data['order_id']));
        if(!$id) {
            throw new Box_Exception('Order not found');
        }
        return $id;
    }
}