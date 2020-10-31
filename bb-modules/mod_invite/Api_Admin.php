<?php
class Box_Mod_Invite_Api_Admin extends Api_Abstract
{
    /**
     * Get information about client my inviter
     * 
     * @param int $client_id - client id to check
     * 
     * @return mixed
     */
    public function inviter($data)
    {
        if(!isset($data['client_id'])) {
            throw new Box_Exception('Client id is required to check invitation status');
        }
        
        $invited = R::findOne('mod_invite_log', 'client_id = :cid', array('cid'=>$data['client_id']));
        if(!$invited) {
            return false;
        }
        
        return array(
            'id'    =>  $invited->id,
            'first_name' =>  $invited->inviter_first_name,
            'last_name'  =>  $invited->inviter_last_name,
        );
    }
    
    public function issue($data)
    {
        if(!isset($data['client_id'])) {
            throw new Box_Exception('Client id is required to check invitation status');
        }
        
        $this->getApiAdmin()->client_get(array('id'=>$data['client_id']));
        
        $amount = isset($data['amount']) ? (int)$data['amount'] : 1;
        $service = new Box_Mod_Invite_Service;
        $service->issueInvites($data['client_id'], $amount);
        
        $this->_log('Issued %s invites for client #%s', $data['amount'], $data['client_id']);
        return true;
    }
        
}