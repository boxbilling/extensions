<?php
/**
 * Clickatell BoxBilling module
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
class Box_Mod_Clickatell_Api_Admin extends Api_Abstract
{
    /**
     * Send SMS via Clickatell web service
     * 
     * @param string $to - phone number to send sms to
     * @param string $text - sms text message
     * 
     * @return bool
     */
    public function send($data)
    {
        if(!isset($data['to'])) {
            throw new Box_Exception("Phone number parameter (to) is missing");
        }
        
        if(!is_numeric($data['to'])) {
            throw new Box_Exception("Phone number is not valid. Only numeric values are allowed");
        }
        
        if(!isset($data['text'])) {
            throw new Box_Exception("SMS text is missing");
        }
        
        $mod = new Box_Mod('clickatell');
        $config = $mod->getConfig();
        
        $params = array(
            'user'      =>  $config['user'],
            'password'  =>  $config['password'],
            'api_id'    =>  $config['api_id'],
            'to'        =>  $data['to'],
            'text'      =>  $data['text'],
        );
        $url = 'http://api.clickatell.com/http/sendmsg?'.http_build_query($params);
        
        $ret = file_get_contents($url);
        if(BB_DEBUG) error_log('Clickatell response: '.$ret);
        
        $sess = explode(":",$ret);
        if ($sess[0] != "ID") {
            throw new Box_Exception("Clickatell: :error", array(':error' => $ret));
        }
        
        $this->_log('Clickatell SMS %s to %s with text: %s', $ret, $data['to'], $data['text']);
        
        return true;
    }
}