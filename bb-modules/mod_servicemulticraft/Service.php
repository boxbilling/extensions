<?php

class Box_Mod_Servicemulticraft_Service
{
    public function install()
    {
        $table = Doctrine_Core::getTable('Model_Setting');
        if(!$table->isPro()) {
            throw new Exception('This extension can only be enabled by PRO license owners', 509);
        }
        
        $sql="
        CREATE TABLE IF NOT EXISTS `service_multicraft` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `client_id` bigint(20) DEFAULT NULL,
        `server_id` int(20) DEFAULT NULL,
        `created_at` varchar(35) DEFAULT NULL,
        `updated_at` varchar(35) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`),
        KEY `client_id_idx` (`client_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ";
        Box_Db::getRb();
        R::exec($sql);
    }
    
    public function toApiArray($row)
    {
        if($row instanceof RedBean_OODBBean) {
            $row = $row->export();
        }
        
        return $row;
    }
    
    /**
     * @param $order
     * @return void
     */
    public function create($order)
    {
        $model = R::dispense('service_minecraft');
        $model->client_id    = $order->client_id;
        $model->created_at   = date('c');
        $model->updated_at   = date('c');
        R::store($model);
        return $model;
    }

    /**
     * @param $order
     * @return void
     * @see http://www.multicraft.org/site/page?view=api-doc
     */
    public function activate($order, $model)
    {
        if(!is_object($model)) {
            throw new Box_Exception('Could not activate order. Service was not created', null, 7456);
        }
        
        $oc = json_decode($order->config, 1);
        
        $m = new Box_Mod('api');
        $api = $m->getService()->getApiAdmin();
        $client = $api->client_get(array('id'=>$order->client_id));
        $product = $api->product_get(array('id'=>$order->product_id));
        $pc = $product['config'];
        
        return array();
    }

    /**
     * Suspend order
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
    
    public function mc_connect()
    {
        return $this->_callApi('listUsers');
    }
    
    /**
     * @see http://www.multicraft.org/site/page?view=api-doc
     * @param type $method
     * @param type $params
     * @return \MulticraftAPI 
     */
    private function _callApi($method, $params = array())
    {
        $mod = new Box_Mod('servicemulticraft');
        $config = $mod->getConfig();

        $username   = $config['username'];
        $key        = $config['api_key'];
        $url        = $config['url'];
            
        if(APPLICATION_ENV == 'testing') {
            $username = 'admin';
            $key = '337508bddb0119218c62';
            $url = 'http://mysqlserver2/multicraft';
        }
        
        $url = rtrim($url, '/') . '/api.php';
        require_once(dirname(__FILE__).'/MulticraftAPI.php');
        $api = new MulticraftAPI($url, $username, $key);
        $res = call_user_method_array($method, $api, $params);
        if($res['success'] == false) {
            $e = reset($res['errors']);
            throw new Exception($e);
        }
        if($res['success'] == true) {
            return $res['data'];
        }
        return $res;
    }
}