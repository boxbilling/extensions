<?php
class Box_Mod_Invite_Api_Guest extends Api_Abstract
{
    /**
     * Confirm invitation code
     * 
     * @param string $hash - invitatin code
     * 
     * @return bool
     */
    public function confirm($data)
    {
        if(!isset($data['hash'])) {
            throw new Box_Exception('Invitation code is not provided');
        }
        
        $invite = R::findOne('mod_invite', 'hash = :hash', array('hash'=>$data['hash']));
        if(!$invite) {
            throw new Box_Exception('Invitation was not found');
        }
        
        $invite->ip_confirm   = $this->_ip;
        $invite->confirmed_at = date('c');
        $invite->updated_at   = date('c');
        R::store($invite);
        
        $this->_log($invite->email . ' confirmed invitation from client #'.$invite->client_id);
        
        return true;
    }
}