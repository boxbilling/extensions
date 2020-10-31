<?php
/**
 * GPS management
 */
class Box_Mod_Servicegps_Api_Admin extends Api_Abstract
{
    private $service = null;
    
    protected function init()
    {
        Box_Db::getRb();
        $this->service = Box_Tools::getService('servicegps');
    }
    
    public function update($data)
    {
        $service = $this->getApiAdmin()->order_service(array('id'=>$data['order_id']));
        $s = R::load('service_gps', $service['id']);
        if(isset($data['imei'])) {
            $s->imei = $data['imei'];
        }
        $s->updated_at = date('c');
        R::store($s);
        return true;
    }
    
    public function test_connection($data)
    {
        $this->service->apiConnection();
        return true;
    }
}