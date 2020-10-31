<?php
/**
 * Partner program management
 */
class Box_Mod_Partner_Api_Admin extends Api_Abstract
{
    private $service = null;
    
    protected function init()
    {
        Box_Db::getRb();
        $this->service = new Box_Mod_Partner_Service();
    }
    
    /**
     * Delete partner 
     * 
     * @param int $client_id
     * @return true
     */
    public function delete($data)
    {
        if(!isset($data['client_id'])) {
            throw new Box_Exception('Client id not passed');
        }
        
        $client = R::load('client', $data['client_id']);
        $order_ids = $this->service->getPartnerOrdersIds($client->id);
        
        foreach($order_ids as $oid) {
            $this->service->removePartnerOrder($oid);
        }
        
        $partner = R::findOne('mod_partner', 'client_id = :cid', array('cid'=>$client->id));
        R::trash($partner);
        
        $this->getApiAdmin()->hook_call(array('event'=>'onAfterAdminDeletePartner', 'params'=>array('client_id'=>$client->id)));
        
        $this->_log('Removed partner #%s', $data['client_id']);
        return true;
    }
    
    /**
     * Get partner details
     * 
     * @param int $client_id
     * @return array
     */
    public function get($data)
    {
        if(!isset($data['client_id'])) {
            throw new Box_Exception('Client id not passed');
        }
        
        $partner = R::findOne('mod_partner', 'client_id = :cid', array('cid'=>$data['client_id']));
        if(!is_object($partner)) {
            throw new Box_Exception('Partner not found');
        }
        
        return $this->service->toApiArray($partner, $this->_role);
    }
    
    /**
     * Get paginated list of partners
     * 
     * @return array - paginated list
     */
    public function get_list($data)
    {
        return Box_Db::getPagerResultSet($data, $this->service, $this->_role);
    }
    
    /**
     * Get paginated list of partner orders
     * 
     * @return array - paginated list
     */
    public function orders($data)
    {
        if(!isset($data['client_id'])) {
            throw new Box_Exception('Partner id not passed');
        }
        $ids = $this->service->getPartnerOrdersIds($data['client_id']);
        if(empty($ids)) {
            return array();
        }
        $orders = $this->getApiAdmin()->order_get_list(array('ids'=>$ids));
        return $orders;
    }
    
    /**
     * Update partners profile details
     * 
     * @return boolean
     * @throws Box_Exception 
     */
    public function profile_update($data)
    {
        if(!isset($data['client_id'])) {
            throw new Box_Exception('Client id not passed');
        }
        
        $partner = R::findOne('mod_partner','client_id = :cid', array('cid'=>$data['client_id']));
        if(!is_object($partner)) {
            throw new Box_Exception('Partner not found');
        }
        
        if(isset($data['price'])) {
            $partner->price = $data['price'];
        }
        
        if(isset($data['status'])) {
            $partner->status = $data['status'];
        }
        
        if(isset($data['product_id'])) {
            $partner->product_id = $data['product_id'];
        }
        
        if(isset($data['public'])) {
            $partner->public = $data['public'];
        }
        
        if(isset($data['logo'])) {
            $partner->logo = $data['logo'];
        }
        
        if(isset($data['website'])) {
            $partner->website = $data['website'];
        }
        
        $partner->updated_at = date('c');
        R::store($partner);
        
        $this->_log('Partner updated profile');
        return true;
    }
    
    public function fix_h24_orders()
    {
        $client_id  = 8831;
        $partner_id = 19;
        $product_id = 2;
        
        $sql="
            SELECT id
            FROM  `client_order` 
            WHERE  `product_id` = $product_id
        ";
        $list = R::getAssoc($sql);

        foreach($list as $order_id) {
            $has = "SELECT id 
                FROM extension_meta 
                WHERE extension = 'mod_partner'
                AND rel_type = 'client_order'
                AND rel_id = $order_id
            ";
            
            if(!R::getCell($has)) {
                $isql = "
                INSERT INTO  extension_meta (
                `client_id` ,
                `extension` ,
                `rel_type` ,
                `rel_id` ,
                `meta_key` ,
                `meta_value` ,
                `created_at` ,
                `updated_at`
                )
                VALUES (
                '$client_id',  'mod_partner',  'client_order',  '$order_id',  'partner',  '$partner_id',  '2012-09-04T00:00:00-04:00',  '2012-09-04T00:00:00-04:00'
                );
                ";
                R::exec($isql);
            }
        }
        
        return true;
    }
}