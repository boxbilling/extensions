<?php

class Box_Mod_Servicevpsnet_Service
{
    public function install()
    {
        $sql="
        CREATE TABLE IF NOT EXISTS `service_vpsnet` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `client_id` bigint(20) DEFAULT NULL,
        `vid` varchar(255) DEFAULT NULL,
        `created_at` varchar(35) DEFAULT NULL,
        `updated_at` varchar(35) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `client_id_idx` (`client_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ";
        Box_Db::getRb();
        R::exec($sql);
    }
    
    public function validateOrderData(&$data)
    {
        
    }
    
    /**
     * @param $order
     * @return void
     */
    public function create($order)
    {
        $model = R::dispense('service_vpsnet');
        $model->client_id    = $order->client_id;
        $model->created_at   = date('c');
        $model->updated_at   = date('c');
        R::store($model);
        return $model;
    }

    /**
     * @param $order
     * @return void
     */
    public function activate($order, $model)
    {
        if(!is_object($model)) {
            throw new Box_Exception('Could not activate order. Service was not created', null, 7456);
        }
        
        $c = json_decode($order->config, 1);
        $this->validateOrderData($c);
        
        
        // call api if not import action
        if(!isset($oc['import']) || !$oc['import']) {
            
        }
        
        $model->vid         = $c['vid'];
        $model->updated_at  = date('c');
        R::store($model);
        
        return true;
    }

    /**
     * Suspend VPS
     * 
     * @param $order
     * @return void
     */
    public function suspend($order, $model)
    {
        if(!is_object($model)) {
            throw new Box_Exception('Could not suspend order. Service was not created', null, 7456);
        }

        $model->updated_at = date('c');
        R::store($model);
        return true;
    }

    /**
     * @param $order
     * @return void
     */
    public function unsuspend($order, $model)
    {
        if(!is_object($model)) {
            throw new Box_Exception('Could not unsuspend order. Service was not created', null, 7456);
        }
        
        $model->updated_at = date('c');
        R::store($model);
        return true;
    }

    /**
     * @param $order
     * @return void
     */
    public function delete($order, $model)
    {
        if(is_object($model)) {
            R::trash($model);
        }
        return true;
    }
    
    public function getApi($type = null)
    {
        $mod = new Box_Mod('servicevpsnet');
        $config = $mod->getConfig();
        require_once BB_PATH_MODS . '/mod_servicevpsnet/vpsnetapi/VPSNET.php';
        return VPSNET::getInstance($config['api_username'], $config['api_password']);
    }
    
    public function getApiVirtualMachine($model)
    {
        $this->getApi();
        $vm = new VirtualMachine;
        $vm->id = $model->vid;
        return $vm;
    }
    
    public function toApiArray($row)
    {
        return $row->export();
    }
}