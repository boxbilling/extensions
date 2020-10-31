<?php
/**
 * GPS management
 */
class Box_Mod_Servicegps_Api_Client extends Api_Abstract
{
    private $service = null;
    
    protected function init()
    {
        Box_Db::getRb();
        $this->service = Box_Tools::getService('servicegps');
    }
    
    private function _getService($data)
    {
        if(!isset($data['order_id'])) {
            throw new Box_Exception('Order id is required');
        }
        
        $order = R::findOne('client_order',
                "id=:id 
                 AND client_id = :cid
                 AND service_type = 'gps'
                ", 
                array('id'=>$data['order_id'], 'cid'=>$this->_identity->id));
        
        if(!$order) {
            throw new Box_Exception('GPS tracker order not found');
        }
        
        $s = R::findOne('service_gps',
                'id=:id AND client_id = :cid',
                array('id'=>$order->service_id, 'cid'=>$this->_identity->id));
        if(!$s) {
            throw new Box_Exception('Order is not activated');
        }
        return array($order, $s);
    }
    
    public function last_position($data)
    {
        list($order, $service) = $this->_getService($data);
        $result = $this->service->apiLastPosition($service);
        return $result;
    }
    
    public function data_tracker($data)
    {
        list($order, $service) = $this->_getService($data);
        $result = $this->service->apiDataTracker($service, $data);
        return $result;
    }
}