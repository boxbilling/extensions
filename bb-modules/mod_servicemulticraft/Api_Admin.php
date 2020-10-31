<?php
/**
 * Multicraft management
 */
class Box_Mod_Servicemulticraft_Api_Admin extends Api_Abstract
{
    private $service = null;
    
    protected function init()
    {
        Box_Db::getRb();
        $mod = new Box_Mod('servicemulticraft');
        $this->service = $mod->getService();
    }
    
    /**
     * Test connection to server
     * 
     * @return boolean
     * @throws Exception 
     */
    public function test_connection()
    {
        return $this->service->mc_connect();
    }
    
    /**
     * Update existing order service
     * 
     * @return boolean 
     */
    public function update($data)
    {
        list($order, $s) = $this->_getService($data);
        
        if(isset($data['username'])) {
            $s->username = $data['username'];
        }
        
        if(isset($data['pass'])) {
            $s->pass = $this->service->encryptPass($data['pass']);
        }
        
        $s->updated_at = date('c');
        R::store($s);
        
        $this->_log('Updated MultiCraft service %s details', $s->id);
        return true;
    }
    
    private function _getService($data)
    {
        if(!isset($data['order_id'])) {
            throw new Box_Exception('Order id is required');
        }
        
        $order = R::findOne('client_order',
                "id=:id 
                 AND service_type = 'multicraft'
                ", 
                array('id'=>$data['order_id']));
        
        if(!$order) {
            throw new Box_Exception('Centova Cast order not found');
        }
        
        $s = R::findOne('service_multicraft',
                'id=:id',
                array('id'=>$order->service_id));
        if(!$s) {
            throw new Box_Exception('Order :id is not activated', array(':id'=>$order->id));
        }
        return array($order, $s);
    }
}