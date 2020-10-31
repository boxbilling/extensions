<?php
class Box_Mod_Massmailer_Api_Admin extends Api_Abstract
{
    protected function init()
    {
        Box_Db::getRb();
    }
    
    /**
     * Get paginated list of active mail messages
     *
     * @optional string $status - filter list by status
     * @optional string $search - search query to search for mail messages
     * 
     * @return array
     */
    public function get_list($data)
    {
        $service = new Box_Mod_Massmailer_Service;
        return Box_Db::getPagerResultSet($data, $service, $this->_role);
    }

    /**
     * Get mail message by id
     *
     * @param int $id - mail message ID
     *
     * @return array
     */
    public function get($data)
    {
        $model = $this->_getMessage($data);
        $service = new Box_Mod_Massmailer_Service;
        return $service->toApiArray($model);
    }

    /**
     * Update mail message
     *
     * @param int $id - mail message id
     *
     * @optional string $subject - mail message title
     * @optional string $content - mail message content
     * @optional string $status - mail message status
     * @optional string $from_name - mail message email from name
     * @optional string $from_email - mail message email from email
     * @optional array $filter  - filter parameters to select clients
     * 
     * @return bool
     */
    public function update($data)
    {
        $model = $this->_getMessage($data);

        if(isset($data['content'])) {
            $model->content = $data['content'];
        }

        if(isset($data['subject'])) {
            $model->subject = $data['subject'];
        }

        if(isset($data['status'])) {
            $model->status = $data['status'];
        }
        
        if(isset($data['filter'])) {
            $model->filter = json_encode($data['filter']);
        }

        if(isset($data['from_name'])) {
            if(empty($data['from_name'])) {
                throw new Box_Exception('Message from name can not be empty');
            }
            $model->from_name = $data['from_name'];
        }
        
        if(isset($data['from_email'])) {
            $validator = new Box_Validate();
            $validator->isEmailValid($data['from_email']);
            $model->from_email = $data['from_email'];
        }
        
        $model->updated_at = date('c');
        R::store($model);

        $this->_log('Updated mail message #%s', $model->id);
        return TRUE;
    }

    /**
     * Create mail message
     *
     * @param string $subject - mail message subject
     * 
     * @optional string $content - mail message content
     *
     * @return bool
     */
    public function create($data)
    {
        if(!isset($data['subject'])) {
            throw new Box_Exception('Message subject not passed');
        }

        $default_content = '{% filter markdown %}
Hi {{ c.first_name }} {{ c.last_name }},

Your email is: {{ c.email }}

Aenean vut sagittis in natoque tortor. Facilisis magnis duis nec eros! Augue 
sed quis tortor porttitor? Rhoncus tortor pid et a enim dis adipiscing eros 
facilisis nunc. Phasellus dis odio lacus pulvinar vel lundium dapibus turpis.

Urna parturient, ultricies nascetur? Et a. Elementum in dapibus ut vel ut 
magna tempor, dapibus lacus sed? Ut velit dignissim placerat, tristique pid 
vut amet et nunc! Elementum dolor, dictumst porta ultrices. Rhoncus, amet. 

Order our services at {{ "order"|link }}

{{ guest.system_company.name }} - {{ guest.system_company.signature }}
{% endfilter %}
        ';
        
        $company = $this->getApiGuest()->system_company();
        
        $model = R::dispense('mod_massmailer');
        $model->from_email = $company['email'];
        $model->from_name = $company['name'];
        $model->subject = $data['subject'];
        $model->content = isset($data['content']) ? $data['content'] : $default_content;
        $model->status = 'draft';
        $model->created_at = date('c');
        $model->updated_at = date('c');
        
        R::store($model);
        
        $this->_log('Created mail message #%s', $model->id);
        return $model->id;
    }

    /**
     * Send test mail message by ID to client
     *  
     * @param int $id - mail message ID
     *
     * @return bool
     */
    public function send_test($data)
    {
        $model = $this->_getMessage($data);
        $client_id = $this->_getTestClientId();
        
        if(empty($model->content)) {
            throw new Box_Exception('Add some content before sending message');
        }
        
        $service = new Box_Mod_Massmailer_Service;
        $service->sendMessage($model, $client_id);
        
        $this->_log('Sent test mail message #%s to client ', $model->id);
        return true;
    }

    /**
     * Send mail message by ID
     *
     * @param int $id - mail message ID
     *
     * @return bool
     */
    public function send($data)
    {
        $model = $this->_getMessage($data);
        
        if(empty($model->content)) {
            throw new Box_Exception('Add some content before sending message');
        }
        
        $mod = new Box_Mod('massmailer');
        $c = $mod->getConfig();
        $interval = isset($c['interval']) ? (int)$c['interval'] : 30;
        $max = isset($c['limit']) ? (int)$c['limit'] : 25;
        
        $api = $this->getApiAdmin();
        $service = new Box_Mod_Massmailer_Service;
        $clients = $service->getMessageReceivers($model, $data);
        foreach($clients as $c) {
            $d = array(
                'queue'     => 'massmailer', 
                'mod'       => 'massmailer',
                'handler'   => 'sendMail',
                'max'       => $max,
                'interval'  => $interval,
                'params'    => array('msg_id'=>$model->id, 'client_id'=>$c['id']),
            );
            $api->queue_message_add($d);
        }
        
        $model->status = 'sent';
        $model->sent_at = date('c');
        R::store($model);
        
        $this->_log('Added mass mail messages #%s to queue', $model->id);
        return true;
    }
        
    /**
     * Copy mail message by ID
     *
     * @param int $id - mail message ID
     *
     * @return bool
     */
    public function copy($data)
    {
        $model = $this->_getMessage($data);
        
        $copy = R::dup($model);
        $copy->subject = $model->subject . ' (Copy)';
        $copy->status = 'draft';
        R::store($copy);
        
        $this->_log('Copied mail message #%s to #%s', $model->id, $copy->id);
        return $copy->id;
    }

    /**
     * Get message receivers list
     *
     * @return array
     */
    public function receivers($data)
    {
        $model = $this->_getMessage($data);
        $service = new Box_Mod_Massmailer_Service;
        return $service->getMessageReceivers($model, $data);
    }
    
    /**
     * Delete mail message by ID
     *
     * @param int $id - mail message ID
     *
     * @return bool
     */
    public function delete($data)
    {
        $model = $this->_getMessage($data);
        $id = $model->id;
        
        R::trash($model);
        
        $this->_log('Removed mail message #%s', $id);
        return true;
    }
    
    /**
     * Generate preview text
     * 
     * @param int $id - message id
     * 
     * @return array - parsed subject and content strings
     */
    public function preview($data)
    {
        $model = $this->_getMessage($data);
        $client_id = $this->_getTestClientId();
        $service = new Box_Mod_Massmailer_Service;
        list($ps, $pc) = $service->getParsed($model, $client_id);
        return array(
            'subject'   =>  $ps,
            'content'   =>  $pc,
        );
    }
    
    private function _getTestClientId()
    {
        $mod = new Box_Mod('massmailer');
        $c = $mod->getConfig();
        
        if(!isset($c['test_client_id']) || empty($c['test_client_id'])) {
            throw new Box_Exception('Client ID needs to be configured in mass mailer settings.');
        }
        
        return (int)$c['test_client_id'];
    }
    
    private function _getMessage($data)
    {
        if(!isset($data['id'])) {
            throw new Box_Exception('Message id not passed');
        }

        $model = R::findOne('mod_massmailer', 'id = :id', array('id'=>$data['id']));
        if(!$model) {
            throw new Box_Exception('Message not found');
        }
        
        return $model;
    }
}