<?php
/**
 * Phoneconfirmation BoxBilling module
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
class Box_Mod_Phoneconfirmation_Service
{
    public function install()
    {
        $sql="
        CREATE TABLE IF NOT EXISTS `mod_phoneconfirmation` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `client_id` bigint(20) DEFAULT NULL,
        `phone` varchar(255) DEFAULT NULL,
        `code` varchar(255) DEFAULT NULL,
        `status` varchar(255) DEFAULT NULL,
        `approved_at` varchar(35) DEFAULT NULL,
        `created_at` varchar(35) DEFAULT NULL,
        `updated_at` varchar(35) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `client_id_idx` (`client_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ";
        Box_Db::getRb();
        R::exec($sql);
    }
    
    public static function onBeforeClientSignUp(Box_Event $event)
    {
        $params = $event->getParameters();
        $phone = $params['phone_cc'] . $params['phone'];
        if(empty($phone)) {
            throw new Box_Exception('Phone number can not be left blank');
        }
        
        $b = R::findOne('mod_phoneconfirmation', 'phone = :p AND status = :status', array('p'=>$phone, 'status'=>'approved'));
        if($b) {
            throw new Box_Exception('Phone number is already registered');
        }
    }
    
    public static function onBeforeClientProfileUpdate(Box_Event $event)
    {
        $api = $event->getApiAdmin();
        $params = $event->getParameters();
        $new_phone = $params['phone_cc'] . $params['phone'];
        
        $client = $api->client_get(array('id'=>$params['id']));
        $current_phone = $client['phone_cc'] . $client['phone'];
        
        if($current_phone != $new_phone) {
            
            //check if client number is already approved. Disable change
            $b = R::findOne('mod_phoneconfirmation', 'phone = :p AND status = :status', array('p'=>$current_phone, 'status'=>'approved'));
            if($b) throw new Box_Exception('Phone number can not be changed. It is already approved');
            
            // if client has already tried to confirm phone number then allow change only once
            $tries = R::getCell('SELECT COUNT(id) FROM mod_phoneconfirmation WHERE client_id = :cid', array('cid'=>$params['id']));
            if($tries > 1) throw new Box_Exception('Phone number can only be changed once.');
        }
        
        //check if other client has the same approved number
        $b = R::findOne('mod_phoneconfirmation', 'phone = :p AND status = :status AND client_id != :cid', array('p'=>$new_phone, 'status'=>'approved', 'cid'=>$params['id']));
        if($b) {
            throw new Box_Exception('Phone number is already registered');
        }
        
        
    }
}