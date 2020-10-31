<?php

class Box_Mod_Massmailer_Service
{
    public function install()
    {
        $mod = new Box_Mod('api');
        $api = $mod->getService()->getApiAdmin();
        $api->extension_activate(array('id'=>'queue', 'type'=>'mod'));
        
        $sql="
        CREATE TABLE IF NOT EXISTS `mod_massmailer` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `from_email` varchar(255) DEFAULT NULL,
        `from_name` varchar(255) DEFAULT NULL,
        `subject` varchar(255) DEFAULT NULL,
        `content` text DEFAULT NULL,
        `filter` text DEFAULT NULL,
        `status` varchar(255) DEFAULT NULL,
        `sent_at` varchar(35) DEFAULT NULL,
        `created_at` varchar(35) DEFAULT NULL,
        `updated_at` varchar(35) DEFAULT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ";
        Box_Db::getRb();
        R::exec($sql);
        
        //default config values
        $api->extension_config_save(array('ext'=>'mod_massmailer', 'limit'=>'2','interval'=>'10', 'test_client_id'=>1));
    }
    
    public function getSearchQuery($data)
    {
        $sql="SELECT *
            FROM mod_massmailer
            WHERE 1 ";
        
        $params = array();
        
        $search = (isset($data['search']) && !empty($data['search'])) ? $data['search'] : NULL;
        $status = isset($data['status']) ? $data['status'] : NULL;
        
        if(NULL !== $status) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }
        
        if(NULL !== $search) {
            $sql .= ' AND (subject LIKE :search OR content LIKE :search OR from_email LIKE :search OR from_name LIKE :search)';
            $params['search'] = '%'.$search.'%';
        }
        
        $sql .= ' ORDER BY created_at DESC';
        
        return array($sql, $params);
    }
    
    public function getMessageReceivers($model, $data = array())
    {
        $row = $this->toApiArray($model);
        $filter = $row['filter'];
        
        $sql="SELECT c.id, c.first_name, c.last_name 
            FROM client c
            LEFT JOIN client_order co ON (co.client_id = c.id)
            WHERE 1
        ";
        
        $values = array();
        if(!empty($filter)) {
            if(isset($filter['client_status']) && !empty($filter['client_status'])) {
                $sql .= sprintf(" AND c.status IN ('%s')", implode("', '", $filter['client_status']));
            }
            
            if(isset($filter['client_groups']) && !empty($filter['client_groups'])) {
                $sql .= sprintf(" AND c.client_group_id IN ('%s')", implode("', '", $filter['client_groups']));
            }
            
            if(isset($filter['has_order']) && !empty($filter['has_order'])) {
                $sql .= sprintf(" AND co.product_id IN ('%s')", implode("', '", $filter['has_order']));
            }
            
            if(isset($filter['has_order_with_status']) && !empty($filter['has_order_with_status'])) {
                $sql .= sprintf(" AND co.status IN ('%s')", implode("', '", $filter['has_order_with_status']));
            }
        }
        
        $sql .= ' GROUP BY c.id ORDER BY c.id DESC';
        
        if(isset($data['debug']) && $data['debug']) {
            throw new Exception($sql. ' '. print_r($values, 1));
        }
        
        return R::getAll($sql, $values);
    }
    
    public function getParsed($model, $client_id)
    {
        $mod = new Box_Mod('api');
        $api = $mod->getService()->getApiAdmin();
        
        $client = $api->client_get(array('id'=>$client_id));
        
        $vars = array();
        $vars['c'] = $client;
        $vars['_tpl'] = $model->subject;
        $ps = $api->system_string_render($vars);
        
        $vars = array();
        $vars['c'] = $client;
        $vars['_tpl'] = $model->content;
        $pc = $api->system_string_render($vars);
        
        return array($ps, $pc);
    }
    
    public function sendMessage($model, $client_id)
    {
        list($ps, $pc) = $this->getParsed($model, $client_id);
        
        $mod = new Box_Mod('api');
        $api = $mod->getService()->getApiAdmin();
        $client = $api->client_get(array('id'=>$client_id));
        
        $data = array(
            'to'            =>  $client['email'],
            'to_name'       =>  $client['first_name'] . ' '.$client['last_name'],
            'from'          =>  $model->from_email,
            'from_name'     =>  $model->from_name,
            'subject'       =>  $ps,
            'content'       =>  $pc,
            'client_id'     =>  $client_id,
        );

        return $api->email_send($data);
    }
    
    public function toApiArray($row)
    {
        if($row instanceof RedBean_OODBBean) {
            $row = $row->export();
        }
        
        if($row['filter']) {
            $row['filter'] = json_decode($row['filter'], 1);
        } else {
            $row['filter'] = array();
        }
        
        return $row;
    }
    
    public static function onAfterAdminCronRun(Box_Event $event)
    {
        try {
            $event->getApiAdmin()->queue_execute(array('queue'=>'massmailer'));
        } catch(Exception $e) {
            error_log('Error executing massmailer queue: '.$e->getMessage());
        }
    }
    
    public function sendMail($params)
    {
        $model = R::findOne('mod_massmailer', 'id = :id', array('id'=>$params['msg_id']));
        if(!$model) {
            throw new Exception('Mass mail message not found');
        }
        $this->sendMessage($model, $params['client_id']);
    }
}