<?php

class Box_Mod_Partner_Service
{
    /**
     * Return partner price for license
     * @todo add more logic when partner has more sales
     * @param float
     */
    public function getSearchQuery($filter)
    {
        $sql = 'SELECT * FROM mod_partner WHERE 1=1';
        
        $params = array();
        $only_public = isset($filter['only_public']) ? (bool)$filter['only_public'] : false;
        $status = isset($filter['status']) ? $filter['status'] : NULL;
        
        if($status) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }
        
        if($only_public) {
            $sql .= ' AND public = 1';
        }
        
        return array($sql, $params);
    }
    
    /**
     * Return partner price for license
     * @todo add more logic when partner has more sales
     * @param float
     */
    public function getPartnerPrice($client)
    {
        $partner = $this->getPartner($client);
        if($partner->price !== null) {
            return $partner->price;
        }
        
        $sales = $this->getSalesCount($client);
        
        if ($sales <= 5) {
            return 5.95;
        }
        
        if ($sales < 10) {
            return 4.95;
        }
        
        if ($sales < 50) {
            return 3.95;
        }
        
        if ($sales < 100) {
            return 2.95;
        }
        
        return 1.95;
    }
    
    public function getSalesCount($client)
    {
        $order_ids = $this->getPartnerOrdersIds($client->id);
        if(empty($order_ids)) {
            return 0;
        }
        
        $q=sprintf("SELECT COUNT(id) FROM client_order WHERE id IN (%s) AND status = 'active'", implode(', ',$order_ids));
        return R::getCell($q);
    }
    
    public function removePartnerOrder($order_id)
    {
        $sql = "DELETE FROM extension_meta WHERE extension = 'mod_partner' AND rel_type='client_order' AND rel_id = :id ";
        R::exec($sql, array('id'=>$order_id));
    }
    
    public function recalculatePricesIfPartnerOrder($order_id)
    {
        $sql="SELECT client_id 
            FROM extension_meta 
            WHERE extension = 'mod_partner' 
            AND rel_type='client_order' 
            AND rel_id = :order_id
        ";
        $client_id = R::getCell($sql, array('order_id'=>$order_id));
        if(!$client_id) {
            //order is not partner order
            return false;
        }
        
        $client = R::load('client', $client_id);
        $price = $this->getPartnerPrice($client);
        
        $mod = new Box_Mod('api');
        $api = $mod->getService()->getApiAdmin();
        
        $ids = $this->getPartnerOrdersIds($client_id);
        foreach($ids as $oid) {
            try {
                $api->order_update(array('id'=>$oid, 'price'=>$price));
                $api->order_status_history_add(array('id'=>$oid, 'status'=>'active', 'notes'=>'Changed order price to '.$price));
            } catch(Exception $e) {
//                error_log($e->getMessage());
            }
        }
    }
    
    public function addOrderForPartner($client, $order_id)
    {
        $partner = $this->getPartner($client);
        
        $c = R::dispense('extension_meta');
        $c->extension = 'mod_partner';
        $c->client_id = $client->id;
        $c->rel_type = 'client_order';
        $c->rel_id = $order_id;
        $c->meta_key = 'partner';
        $c->meta_value = $partner->id;
        $c->created_at = date('c');
        $c->updated_at = date('c');
        R::store($c);
    }
    
    public function getPartnerOrdersIds($client_id)
    {
        $sql="SELECT rel_id 
            FROM extension_meta 
            WHERE extension = 'mod_partner' 
            AND rel_type='client_order'
            AND client_id = :cid";
        return R::getAssoc($sql, array('cid'=>$client_id));
    }
    
    /**
     * Return list of orders grouped by partner_id
     * @return array 
     */
    public function getPartnersOrdersIds()
    {
        $sql="SELECT client_id, rel_id FROM extension_meta WHERE extension = 'mod_partner' AND rel_type='client_order'";
        $ids = R::getAll($sql);
        
        $result = array();
        foreach($ids as $r) {
            $result[$r['client_id']][] = $r['rel_id'];
        }
        return $result;
    }
    
    public function createPartner($client)
    {
        if($this->isPartner($client)) {
            return true;
        }
        
        $meta = R::dispense('mod_partner');
        $meta->client_id = $client->id;
        $meta->status = 'active';
        $meta->public = false;
        $meta->created_at = date('c');
        $meta->updated_at = date('c');
        R::store($meta);
        
        return true;
    }
    
    public function getPartner($client)
    {
        return R::findOne('mod_partner', 'client_id = :cid', array('cid'=>$client->id));
    }
    
    public function isPartner($client)
    {
        $sql="
            SELECT id
            FROM mod_partner
            WHERE client_id = :cid
        ";
        $id = R::getCell($sql, array('cid'=>$client->id));
        return !empty($id);
    }
    
    public function getNextInvoiceDate()
    {
        $curMonth = date('n');
        $curYear  = date('Y');
        if ($curMonth == 12)
            $firstDayNextMonth = mktime(1, 1, 1, 1, 1, $curYear+1);
        else
            $firstDayNextMonth = mktime(0, 0, 0, $curMonth+1, 1);
        return date('c', $firstDayNextMonth);
    }
    
    public function toApiArray($row, $role = 'guest', $deep = true)
    {
        if($row instanceof RedBean_OODBBean) {
            $row = $row->export();
        }
        
        $client = R::findOne('client', 'id=:id', array('id'=>$row['client_id']));
        $clienta = $client->export();
        $data = array(
            'logo'          => $row['logo'],
            'website'       => $row['website'],
            
            'first_name'    => $clienta['first_name'],
            'last_name'     => $clienta['last_name'],
            'company'       => $clienta['company'],
            'phone_cc'      => $clienta['phone_cc'],
            'phone'         => $clienta['phone'],
            'address_1'     => $clienta['address_1'],
            'address_2'     => $clienta['address_2'],
            'country'       => $clienta['country'],
            'state'         => $clienta['state'],
            'postcode'      => $clienta['postcode'],
            
            'created_at'    => $row['created_at'],
            'updated_at'    => $row['updated_at'],
        );
        
        if($role == 'admin' || $role == 'client') {
            $data['client_id']  = $row['client_id'];
            $data['public']     = $row['public'];
            $data['product_id'] = $row['product_id'];
            $data['status']     = $row['status'];
            $data['sales']      = $this->getSalesCount($client);
            $data['price']      = $row['price'];
            $data['email']      = $clienta['email'];
            $data['selling_price'] = $this->getPartnerPrice($client);
        }
        
        return $data;
    }
    
    
    
    /** EVENTS  **/
    
    public static function onAfterClientBecomePartner(Box_Event $event)
    {
        $api = $event->getApiAdmin();
        $params = $event->getParameters();
        
        $s = new self;
        $client = R::load('client', $params['client_id']);
        $partner = $s->getPartner($client);
        
        $email = array();
        $email['to_client'] = $params['client_id'];
        $email['code'] = 'mod_partner_signup';
        $email['partner'] = $s->toApiArray($partner, 'client');
        
        $api->email_template_send($email);
    }
    
    /*
    public static function onAfterPartnerOrderCreate(Box_Event $event)
    {
        $api = $event->getApiAdmin();
        $params = $event->getParameters();
        
        $s = new self;
        $client = R::load('client', $params['client_id']);
        $partner = $s->getPartner($client);
        $order = $api->order_get(array('id'=>$params['order_id']));
        $service = $api->order_service(array('id'=>$params['order_id']));
        
        $email = array();
        $email['to_client'] = $params['client_id'];
        $email['code'] = 'mod_partner_purchase';
        $email['partner'] = $s->toApiArray($partner, 'client');
        $email['order'] = $order;
        $email['service'] = $service;
        
        $api->email_template_send($email);
    }
    */
    
    /**
     * Delete order relation with partner after order is removed
     * @param Box_Event $event 
     */
    public static function onAfterAdminOrderDelete(Box_Event $event)
    {
        try {
            $params = $event->getParameters();
            $s = new self();
            $s->recalculatePricesIfPartnerOrder($params['id']);
            $s->removePartnerOrder($params['id']);
        } catch(Exception $e) {
            error_log($e->getMessage());
        }
    }
    
    public static function onAfterAdminOrderRenew(Box_Event $event)
    {
        $params = $event->getParameters();
        $s = new self();
        $s->recalculatePricesIfPartnerOrder($params['id']);
    }
    
    public static function onAfterAdminOrderSuspend(Box_Event $event)
    {
        $params = $event->getParameters();
        $s = new self();
        $s->recalculatePricesIfPartnerOrder($params['id']);
    }
    
    public static function onAfterAdminOrderUnsuspend(Box_Event $event)
    {
        $params = $event->getParameters();
        $s = new self();
        $s->recalculatePricesIfPartnerOrder($params['id']);
    }
    
    public static function onAfterAdminOrderCancel(Box_Event $event)
    {
        $params = $event->getParameters();
        $s = new self();
        $s->recalculatePricesIfPartnerOrder($params['id']);
    }
    
    public static function onAfterAdminOrderUncancel(Box_Event $event)
    {
        $params = $event->getParameters();
        $s = new self();
        $s->recalculatePricesIfPartnerOrder($params['id']);
    }

}