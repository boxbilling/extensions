<?php
/**
 * VPS.NET management
 */
class Box_Mod_Servicevpsnet_Api_Admin extends Api_Abstract
{
    private $service = null;
    
    protected function init()
    {
        $this->service = Box_Tools::getService('servicevpsnet');
    }
    
    private function _getService($data)
    {
        if(!isset($data['order_id'])) {
            throw new Box_Exception('Order id is required');
        }
        
        $order = R::findOne('client_order',
                "id=:id 
                 AND service_type = 'vpsnet'
                ", 
                array('id'=>$data['order_id']));
        
        if(!$order) {
            throw new Box_Exception('VPS.net order not found');
        }
        
        $s = R::findOne('service_vpsnet',
                'id=:id',
                array('id'=>$order->service_id));
        if(!$s) {
            throw new Box_Exception('Order :id is not activated', array(':id'=>$order->id));
        }
        return array($order, $s);
    }
    
    /**
     * Test API connection
     * 
     * @return bool
     */
    public function test_connection($data)
    {
        $res = $this->service->getApi()->getProfile();
        if(!$res instanceof stdClass) { 
            throw new Box_Exception('Could not connect to VPS.net API');
        }
        return true;
    }
    
    /**
     * Get templates pairs for order
     * 
     * @param type $data
     * @return boolean
     * @throws Box_Exception
     */
    public function get_templates($data)
    {
        $res = $this->service->getApi()->getAllTemplates();
        return $res;
    }
    
    public function get_info($data)
    {
        list($order, $model) = $this->_getService($data);
        
        $api = $this->service->getApi();
        $api->setAPIResource('virtual_machines/' . $model->vid);
        $result = $api->sendGETRequest();
        if (!is_object($result['response'])) {
            throw new Box_Exception('Could not determine vps details');
        }
                
        return $result['response']->virtual_machine;
    }
    
    public function power_on($data)
    {
        list($order, $model) = $this->_getService($data);
        $vm_api = $this->service->getApiVirtualMachine($model);
        $vm_api->powerOn();
        return true;
    }
    
    public function power_off($data)
    {
        list($order, $model) = $this->_getService($data);
        $vm_api = $this->service->getApiVirtualMachine($model);
        $vm_api->powerOff();
        return true;
    }
    
    public function shutdown($data)
    {
        list($order, $model) = $this->_getService($data);
        $vm_api = $this->service->getApiVirtualMachine($model);
        $vm_api->shutdown();
        return true;
    }
    
    public function reboot($data)
    {
        list($order, $model) = $this->_getService($data);
        $vm_api = $this->service->getApiVirtualMachine($model);
        $vm_api->reboot();
        return true;
    }
    
    public function create_backup($data)
    {
        $label = date('Y-m-d H:i:s');
        list($order, $model) = $this->_getService($data);
        $vm_api = $this->service->getApiVirtualMachine($model);
        $vm_api->createBackup($label);
        return true;
    }
    
    public function reinstall($data)
    {
        if(!isset($data['system_template_id'])) {
            throw new Box_Exception('VPS.net System template id is missing');
        }
        list($order, $model) = $this->_getService($data);
        $vm_api = $this->service->getApiVirtualMachine($model);
        $vm_api->reinstall($data['system_template_id']);
        return true;
    }
}