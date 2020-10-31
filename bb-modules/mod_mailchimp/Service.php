<?php
/**
 * Mailchimp BoxBilling module
 *
 * LICENSE
 *
 * This source file is subject to the license that is bundled
 * with this package in the file LICENSE.txt
 * It is also available through the world-wide-web at this URL:
 * http://www.boxbilling.com/LICENSE.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@boxbilling.com so we can send you a copy immediately.
 *
 * @copyright Copyright (c) 2010-2012 BoxBilling (http://www.boxbilling.com)
 * @license   http://www.boxbilling.com/LICENSE.txt
 * @version   $Id$
 */
class Box_Mod_Mailchimp_Service
{
    public function install()
    {
        $table = Doctrine_Core::getTable('Model_Setting');
        if(!$table->isPro()) {
            throw new Exception('This extension can only be enabled by PRO license owners', 509);
        }
    }
    
    public function mailchimpPing()
    {
        $api = $this->_mailchimpApi();
        $res = $api->ping();
        if ($api->errorCode){
            throw new Exception($api->errorMessage);
        }
        return $res;
    }
    
    public function mailchimpLists()
    {
        return $this->_mailchimpApi()->lists();
    }
    
    public function mailchimpSubscribe($client_id)
    {
        $mod = new Box_Mod('mailchimp');
        $config = $mod->getConfig();
        
        $mod = new Box_Mod('api');
        $api = $mod->getService()->getApiAdmin();
        $client = $api->client_get(array('id'=>$client_id));
        $merge_vars = array('FNAME'=>$client['first_name'], 'LNAME'=>$client['last_name']);

        $listId = $config['list_id'] ;
        $email = $client['email'];
        $api = $this->_mailchimpApi();
        $retval = $api->listSubscribe($listId, $email, $merge_vars, 'html', false);
        if ($api->errorCode){
            throw new Exception($api->errorMessage);
        }
        return $retval;
    }
    
    private function _mailchimpApi()
    {
        $mod = new Box_Mod('mailchimp');
        $config = $mod->getConfig();
        require_once dirname(__FILE__).'/MCAPI.class.php';
        return new MCAPI($config['api_key']);
    }
    
    public static function onAfterClientSignUp(Box_Event $event)
    {
        $params = $event->getParameters();
        try {
            $event->getApiAdmin()->mailchimp_subscribe(array('client_id'=>$params['id']));
        } catch(Exception $exc) {
            error_log($exc->getMessage());
            if($event->getApiGuest()->extension_is_on(array('mod'=>'notification'))) {
                $msg = "Error subscribing client to mailchimp: ".$exc->getMessage();
                $event->getApiAdmin()->notification_add(array('message'=>$msg));
            }
        }
        return true;
    }
}