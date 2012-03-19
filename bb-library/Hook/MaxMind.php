<?php
/**
 * Event Hook example for MaxMind fraud protection
 *
 * To connect it to BoxBilling put it to bb-library/Hook/MaxMind.php
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
class Hook_MaxMind
{
    public static function onBeforeClientCheckout(Box_Event $event)
    {
        $cart = $event->getSubject();
        $params = $event->getParameters();
        $ip     = $params['ip'];
        $client = $params['client'];

        list($t, $domain) = explode('@', $client['email']);
        $rp = array();
        $rp['i']            = $ip;
        $rp['domain']       = $domain;
        $rp['city']         = $client['city'];
        $rp['region']       = $client['state'];
        $rp['postal']       = $client['postcode'];
        $rp['country']      = $client['country'];
        $rp['emailMD5']     = md5($client['email']);
        $rp['txn_type']     = 'paypal';
        $rp['license_key']  = ''; // your MaxMind license key

        $url='https://minfraud2.maxmind.com/app/ccv2r?'.http_build_query($rp);
        $content = file_get_contents($url);
        
        // enable this to debug response to the screen when clicking checkout button
        // throw new Exception(var_export($content, 1));

        $result = array();
        $keyvaluepairs = explode(";",$content);
        $numkeyvaluepairs = count($keyvaluepairs);
        for ($i = 0; $i < $numkeyvaluepairs; $i++) {
            list($key,$value) = explode("=",$keyvaluepairs[$i]);
            $result[$key] = $value;
        }

        // Do something with maxmind result.
        // You can throw an Exception if detected that this cliet is a fraud
        // In this example we simple save MaxMind result to client profile custom field 10.
        $pdo = Box_Db::getPdo();
        $q="UPDATE client
            SET custom_10 = :value
            WHERE id = :client_id
            LIMIT 1";
        $stmt = $pdo->prepare($q);
        $stmt->execute(array('client_id'=>$client['id'], 'value'=>json_encode($result)));
    }
}