<?php

class Box_Mod_Servicegps_Service
{
    public function validateOrderData(&$data)
    {
        if(!isset($data['imei']) || empty($data['imei'])) {
            throw new Exception('IMEI is required');
        }
    }
    
    /**
     * @param $order
     * @return void
     */
    public function create($order)
    {
        $model = R::dispense('service_gps');
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
        
        $model->imei = $c['imei'];
        $model->updated_at   = date('c');
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
    
    public function toApiArray($row)
    {
        return $row->export();
    }
    
    public function apiDataTracker($service, $data)
    {
        $from = (isset($data['date_from']) && !empty($data['date_from'])) ? $data['date_from'] : date('Y-m-d H:i:s',strtotime('-1day'));
        $to = (isset($data['date_to']) && !empty($data['date_to'])) ? $data['date_to'] : date('Y-m-d H:i:s');
        $params = array(
            'imei'  =>  $service->imei,
            'from'  =>  $from,
            'to'    =>  $to,
        );
        error_log(print_r($params, 1));
        $api = $this->getApi();
        return $api->data_tracker($params);
    }
    
    public function apiLastPosition($service)
    {
        $data = array(
            'imei'  =>  $service->imei,
        );
        $api = $this->getApi();
        return $api->data_lastposition($data);
    }
    
    public function apiConnection()
    {
        $api = $this->getApi();
        $api->data_ping();
        return true;
    }
    
    public function getModuleConfig()
    {
        return array(
            'api_username'  =>  '',
            'api_password'  =>  '',
        );
    }
    
    public function getApi()
    {
        $config = $this->getModuleConfig();
        return new GpsApi($config);
    }
    
    public function install()
    {
        $sql="
        CREATE TABLE IF NOT EXISTS `service_gps` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `client_id` bigint(20) DEFAULT NULL,
        `imei` varchar(255) DEFAULT NULL,
        `created_at` varchar(35) DEFAULT NULL,
        `updated_at` varchar(35) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `imei` (`imei`),
        KEY `client_id_idx` (`client_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ";
        Box_Db::getRb();
        R::exec($sql);
    }
}

class GpsApi
{
    protected $_api_url     = 'http://apigps.antanas.eu/api.php';

    public function __construct($options)
    {
        if (!extension_loaded('curl')) {
            throw new Exception('cURL extension is not enabled');
        }
        
        if(isset($options['api_username'])) {
            $this->_api_username = $options['api_username'];
        }
        
        if(isset($options['api_password'])) {
            $this->_api_password = $options['api_password'];
        }
    }

    public function __call($method, $arguments)
    {
        $data = array();
        if(isset($arguments[0]) && is_array($arguments[0])) {
            $data = $arguments[0];
        }
        
        $data['api_method']     = $method;
        $data['api_username']   = $this->_api_username;
        $data['api_password']   = $this->_api_password;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,               $this->_api_url);
        curl_setopt($ch, CURLOPT_POST,              true);
        curl_setopt($ch, CURLOPT_POSTFIELDS,        http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,    true);
        $result = curl_exec($ch);
        
        //error_log($result);
        
        if($result === false) {
            $e = new Exception(sprintf('Curl Error: "%s"', curl_error($ch)));
            curl_close($ch);
            throw $e;
        }
        
        curl_close($ch);

        $json = json_decode($result, 1);
        
        if(!is_array($json)) {
            throw new Exception(sprintf('Invalid Response "%s"', $result));
        }

        if(isset($json['error']) && !empty($json['error'])) {
            throw new Exception($json['error']['message'], $json['error']['code']);
        }

        if(!isset($json['result'])) {
            throw new Exception(sprintf('Invalid Response "%s"', $result));
        }

        return $json['result'];
    }

}