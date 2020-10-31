<?php

class Box_Mod_Droidtweak_Service
{
    public function getSearchQuery($filter)
    {
        $q="SELECT id, client_id, rel_type, rel_id, meta_value, created_at, updated_at
            FROM extension_meta 
            WHERE extension = 'mod_droidtweak'
            AND meta_key = 'order'
            ORDER BY id DESC
        ";
        return array($q, array());
    }
    
    public function toApiArray($row)
    {
        $mod = new Box_Mod('api');
        $api = $mod->getService()->getApiAdmin();
        
        $c = json_decode($row['meta_value'], 1);
        
        $client = $api->client_get(array('id'=>$row['client_id']));
        $product = $api->product_get(array('id'=>$c['product_id']));
        
        $status = isset($c['status']) ? $c['status'] : 'Pending Approval';
        $content = isset($c['content']) ? $c['content'] : '';
        
        $result = $c;
        $result['id']   = $row['id'];
        $result['created_at']   = $row['created_at'];
        $result['updated_at']   = $row['updated_at'];
        $result['client'] = $client;
        $result['product'] = $product;
        $result['status'] = $status;
        $result['content'] = $content;
        return $result;
    }
    
    public function updateReview($id, $data)
    {
        Box_Db::getRb();
        $row = R::findOne('extension_meta', 'id=:id',array('id'=>$id));
        if(!$row) {
            throw new Exception('Review not found');
        }
        $c = json_decode($row->meta_value, 1);
        
        if(isset($data['content'])) {
            $c['content'] = $data['content'];
        }
        
        if(isset($data['status'])) {
            $c['status'] = $data['status'];
        }
        
        $row->meta_value = json_encode($c);
        $row->updated_at = date('c');
        R::store($row);
    }
    
    public function getReview($id)
    {
        Box_Db::getRb();
        $row = R::findOne('extension_meta', 'id=:id',array('id'=>$id));
        if(!$row) {
            throw new Exception('Review not found');
        }
        return $this->toApiArray($row->export());
    }
    
    public static function onAfterAdminCreateClient(Box_Event $event)
    {
        $api = $event->getApiAdmin();
        $params = $event->getParameters();
        
        try {
            $email = array();
            $email['to_client'] = $params['id'];
            $email['code']      = 'mod_droidtweak_client_signup';
            $email['password']  = $params['password'];
            $api->email_template_send($email);
        } catch(Exception $exc) {
            error_log($exc->getMessage());
        }

        return true;
    }
    
    public static function onAfterDroidTweakPayment(Box_Event $event)
    {
        $params = $event->getParameters();
        $s = new self;
        $s->updateReview($params['meta_order'], array('status' => 'Pending Approval'));
        
        $review = $s->getReview($params['meta_order']);
        
        $mod = new Box_Mod('droidtweak');
        $config = $mod->getConfig();
        $api = $event->getApiAdmin();
        $subject        = $config['ticket_subject'];
        $helpdesk_id    = $config['ticket_helpdesk_id'];
        
        $render_params = $review;
        $render_params['_tpl'] = $config['ticket_content'];
        
        $content        = $api->system_string_render($render_params);
        
        $ticket_data = array(
            'client_id'             => $review['client']['id'],
            'content'               => $content,
            'subject'               => $subject,
            'status'                => 'open',
            'support_helpdesk_id'   => $helpdesk_id,
        );
        
        $api->support_ticket_create($ticket_data);
    }
}