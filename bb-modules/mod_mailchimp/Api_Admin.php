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
class Box_Mod_Mailchimp_Api_Admin extends Api_Abstract
{
    protected function init()
    {
        Box_Db::getRb();
        $mod = new Box_Mod('mailchimp');
        $this->service = $mod->getService();
    }
    
    /**
     * Sync clients with mailchimp list
     * 
     * @return string - sync log
     */
    public function sync()
    {
        $sql="SELECT id, email FROM client WHERE 1";
        $res = R::getAll($sql);

        $log = '';
        foreach($res as $c) {
            try {
                $this->service->mailchimpSubscribe($c['id']);
                $log .= sprintf('Client #%s %s synced', $c['id'], $c['email']) . PHP_EOL;
            } catch (Exception $exc) {
                error_log($exc->getMessage());
                $log .= $exc->getMessage() . PHP_EOL;
            }
        }
        
        return $log;
    }
    
    /**
     * Test API connection
     * 
     * @param int $client_id - client id to be subscibed
     * @return bool
     */
    public function subscribe($data)
    {
        if(!isset($data['client_id'])) {
            throw new Box_Exception('Client id is missing');
        }
        return $this->service->mailchimpSubscribe($data['client_id']);
    }
    
    /**
     * Test API connection
     * 
     * @return bool - true if can connect to mailchimp
     */
    public function ping()
    {
        $this->service->mailchimpPing();
        return true;
    }
    
    /**
     * Get lists pairs
     * 
     * @return array
     */
    public function lists()
    {
        $lists = $this->service->mailchimpLists();
        if($lists['total'] == 0) {
            return array();
        }
        
        $res = array();
        foreach($lists['data'] as $list) {
            $res[$list['id']] = $list['name'];
        }
        
        return $res;
    }
}