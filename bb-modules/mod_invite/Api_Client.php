<?php
class Box_Mod_Invite_Api_Client extends Api_Abstract
{
    /**
     * Get information about my invitations
     * 
     * @return array
     */
    public function info()
    {
        $inv = R::findOne('mod_invite_log', 'client_id = :cid', array('cid'=>$this->_identity->id));
        $inviter = null;
        if($inv) {
            $inviter = $inv->inviter_first_name . ' '. $inv->inviter_last_name[0].'.';
        }
        
        $mod = new Box_Mod('invite');
        $config = $mod->getConfig();
        
        return array(
            'message'       =>  $config['message'],
            'invitations'   =>  $this->_getInvitationsLeft(),
            'inviter'       =>  $inviter,
        );
    }
    
    /**
     * Send invitation to email
     * 
     * @param string $email     - email of a friend to be invited
     * 
     * @optional string $message   - email message
     * @optional string $subject   - email subject
     * 
     * @return bool
     */
    public function send($data)
    {
        if(!isset($data['email'])) {
            throw new Box_Exception('Invitation code is not provided');
        }
        
        $validator = new Box_Validate;
        $validator->isEmailValid($data['email']);
        
        $api = $this->getApiAdmin();
        
        $exists = R::findOne('client', 'email = :email', array('email'=>$data['email']));
        if($exists) {
            throw new Box_Exception('Client is already registered. Invitation was not sent');
        }
        
        if($this->_getInvitationsLeft() <= 0) {
            throw new Box_Exception('You do not have any invitation left');
        }
        
        $invite = R::findOne('mod_invite', 'client_id = :cid AND (sent_at IS NULL OR sent_at = "")', 
                array('cid'=>$this->_identity->id));
        $invite->email          = $data['email'];
        $invite->hash           = sha1(uniqid() . microtime() . rand(555, 99999));
        $invite->updated_at     = date('c');
        R::store($invite);
        
        $mod = new Box_Mod('invite');
        $config = $mod->getConfig();
        
        $email = array();
        $email['to']                = $data['email'];
        $email['code']              = 'mod_invite_confirm';
        $email['invite']            = $invite->export();
        $email['message']           = isset($data['message']) ? $data['message'] : $config['message'];
        $email['c']                 = $api->client_get(array('id'=>$invite->client_id));
        
        $this->getApiAdmin()->email_template_send($email);
        
        $invite->ip_send     = $this->_ip;
        $invite->sent_at     = date('c');
        R::store($invite);
        
        $this->_log('Invitation sent to: '.$data['email']);
        return true;
    }
    
    private function _getInvitationsLeft()
    {
        $left = R::getCell('SELECT COUNT(id) 
            FROM mod_invite 
            WHERE client_id = :cid 
            AND (sent_at IS NULL OR sent_at = "")', 
                array('cid'=>$this->_identity->id));
        return ($left) ? $left : 0;
    }
}