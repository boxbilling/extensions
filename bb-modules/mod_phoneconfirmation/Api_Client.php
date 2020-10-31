<?php
/**
 * Phone confirmation BoxBilling module
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
class Box_Mod_Phoneconfirmation_Api_Client extends Api_Abstract
{
    /**
     * Send activation code via sms to client phone nuber defined in profile
     * Using clickatell sms gateway
     * 
     * @return boolean
     * @throws Box_Exception
     */
    public function send_confirmation()
    {
        if($this->is_confirmed()) {
            throw new Box_Exception('You have already confirmed your phone number');
        }
        
        $mod = new Box_Mod('phoneconfirmation');
        $config = $mod->getConfig();
        $sms = $config['sms'];
        if(strpos($sms, '{{code}}') === false) {
            throw new Box_Exception('SMS message must contain {{code}} variable');
        }
        
        $api = $this->getApiAdmin();
        $client = $api->client_get(array('id'=>$this->_identity->id));
        
        $code = Box_Tools::generatePassword(8, 2);
        $phone = $client['phone_cc'] . $client['phone'];
        $text = str_replace('{{code}}', $code, $sms);
        
        $b = R::findOne('mod_phoneconfirmation', 'phone = :phone', 
                array('phone'=>$phone));
        if($b) {
            throw new Box_Exception('Confirmation SMS was already sent to :phone', array(':phone'=>$phone));
        }
        
        if(BB_DEBUG) error_log($phone .': '. $text);
        
        if(APPLICATION_ENV == 'production') 
            $api->clickatell_send(array('to'=>$phone, 'text'=>$text));
        
        $bean = R::dispense('mod_phoneconfirmation');
        $bean->client_id = $client['id'];
        $bean->phone = $phone;
        $bean->code = $code;
        $bean->status = 'pending_confirmation';
        $bean->created_at = date('c');
        $bean->updated_at = date('c');
        R::store($bean);

        $this->_log('Sent phone verification code "%s" for client #%s phone number %s', $code, $client['id'], $phone);
        
        return true;
    }
    
    /**
     * Approve activation code sent via sms message
     * 
     * @param string $code - activation code received via sms
     * @return bool
     */
    public function confirm($data)
    {
        if(!isset($data['code'])) {
            throw new Box_Exception('code parameter is missing');
        }
        
        $bean = R::findOne('mod_phoneconfirmation', 'client_id = :cid AND code = :code', 
                array('cid'=>$this->_identity->id, 'code'=>$data['code']));
        if(!$bean) {
            throw new Box_Exception('Activation code is not valid');
        }
        
        $bean->status = 'approved';
        $bean->approved_at = date('c');
        $bean->updated_at = date('c');
        R::store($bean);
        
        $this->_log('Client #%s approved phone number %s', $this->_identity->id, $bean->phone);
        
        return true;
    }
    
    /**
     * Check if currently logged in client phone number is confirmed
     */
    public function is_confirmed()
    {
        $bean = R::findOne('mod_phoneconfirmation', 'client_id = :cid AND status = :status LIMIT 1', 
                array('cid'=>$this->_identity->id, 'status'=>'approved'));
        if(!$bean) {
            return false;
        }
        return true;
    }
}