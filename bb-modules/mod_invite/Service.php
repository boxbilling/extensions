<?php
class Box_Mod_Invite_Service
{
    public function isInvited($client_email)
    {
        $invitation = R::findOne('mod_invite', 'email = :email 
            AND confirmed_at IS NOT NULL 
            AND confirmed_at != ""', 
            array('email'=>$client_email));
        return ($invitation) ? true : false;
    }
    
    public function issueInvites($client_id, $amount)
    {
        for ($index = 0; $index < (int)$amount; $index++) {
            $bean               = R::dispense('mod_invite');
            $bean->client_id    = $client_id;
            $bean->created_at   = date('c');
            $bean->updated_at   = date('c');
            R::store($bean);
        }
    }
    
    public function install()
    {
        Box_Db::getRb();
        $sql="
        CREATE TABLE IF NOT EXISTS `mod_invite` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `client_id` bigint(20) DEFAULT NULL,
        `email` varchar(255) DEFAULT NULL,
        `hash` varchar(255) DEFAULT NULL,
        `ip_send` varchar(255) DEFAULT NULL,
        `ip_confirm` varchar(255) DEFAULT NULL,
        `confirmed_at` varchar(35) DEFAULT NULL,
        `sent_at` varchar(35) DEFAULT NULL,
        `created_at` varchar(35) DEFAULT NULL,
        `updated_at` varchar(35) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `client_id_idx` (`client_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ";
        R::exec($sql);
        
        $sql2="
        CREATE TABLE IF NOT EXISTS `mod_invite_log` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `client_id` bigint(20) DEFAULT NULL,
        `inviter_id` bigint(20) DEFAULT NULL,
        `inviter_first_name` varchar(255) DEFAULT NULL,
        `inviter_last_name` varchar(255) DEFAULT NULL,
        `created_at` varchar(35) DEFAULT NULL,
        `updated_at` varchar(35) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `client_id_idx` (`client_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ";
        R::exec($sql2);
    }
    
    /**
     * Give client invites if activates orders specific product
     */
    public static function onAfterAdminOrderActivate(Box_Event $event)
    {
        $api = $event->getApiAdmin();
        $params = $event->getParameters();
        $order = $api->order_get(array('id'=>$params['id']));
        
        $mod = new Box_Mod('invite');
        $config = $mod->getConfig();
        $service = $mod->getService();
        
        if(!isset($config['invites_for_activation']) || $config['invites_for_activation'] < 1) {
            return false;
        }
        
        if(is_array($config['products']) 
            && in_array($order['product_id'], $config['products']) 
            && $config['invites_for_activation']) {
            $service->issueInvites($order['client_id'], $config['invites_for_activation']);
        }
    }
    
    /**
     * Check if invites only registration is enabled
     */
    public static function onBeforeClientSignUp(Box_Event $event)
    {
        $params = $event->getParameters();
        
        $mod = new Box_Mod('invite');
        $config = $mod->getConfig();
        $service = $mod->getService();
        
        if(isset($config['invite_only']) && $config['invite_only'] && !$service->isInvited($params['email'])) {
            throw new Box_Exception('Registration is available with invitation only');
        }
    }
    
    /**
     * Check if email has confirmrd invitation
     */
    public static function onAfterClientSignUp(Box_Event $event)
    {
        $mod = new Box_Mod('invite');
        $config = $mod->getConfig();
        $service = $mod->getService();
        
        try {
            $params = $event->getParameters();
            $client = $event->getApiAdmin()->client_get(array('id'=>$params['id']));
            $invitation = R::findOne('mod_invite', 'email = :email 
                AND confirmed_at IS NOT NULL 
                AND confirmed_at != ""', 
                array('email'=>$client['email']));

            if($invitation) {
                
                $inviter = $event->getApiAdmin()->client_get(array('id'=>$invitation->client_id));
                
                $bean               = R::dispense('mod_invite_log');
                $bean->client_id    = $client['id'];
                $bean->inviter_id   = $inviter['id'];
                $bean->inviter_first_name = $inviter['first_name'];
                $bean->inviter_last_name = $inviter['last_name'];
                $bean->created_at   = date('c');
                $bean->updated_at   = date('c');
                R::store($bean);
                R::exec('DELETE FROM mod_invite WHERE email = :email', array('email'=>$client['email']));
            }
            
            // give invitations after registration
            if(isset($config['invites_for_registration']) && $config['invites_for_registration'] > 0) {
                $service->issueInvites($client['id'], $config['invites_for_registration']);
            }
        } catch(Exception $e) {
            error_log($e->getMessage());
        }
    }
}