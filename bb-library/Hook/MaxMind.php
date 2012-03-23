<?php
/**
 * Event Hook for MaxMind fraud protection
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
 * @edited by Evtimiy Mihaylov (evo@vaupe.com)
 * @new functions by Evtimiy Mihaylov (evo@vaupe.com)
 * Note: This Hook includes a field validation addon.
 *
 * Now every order could be checked automatically to MaxMind before it's processed and if there's a suspicion of fraud, the user is not allowed to complete the order.
 * Many new features:
 * - specific check for invalid city or postal code - custom field 9 is used to display the problem with the order
 * - option to disable orders with free e-mails
 * - implemented function that allows new request to MaxMind only if the personal information of the client was changed
 * - configured for the newest version (1.3) of MaxMind
 *
 * Instructions:
 * Select the MyBoxBilling Theme from the theme list, or edit the theme so that it includes these additional fields on registration: country, city, postcode, phone country code, phone and address
 * This MaxMind Event Hook is for MaxMind version 1.3 - please choose version 1.3 from your MaxMind account menu.
 * Enter your MaxMind license key, the requested type of the query and the preferred maximum fraudscore in the parameters below.
 * To disable orders using e-mails with free e-mail providers, uncomment the code below "disable orders with e-mails from a free e-mail providers".
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
        $rp['txnID']       = $client['id'];
        $rp['custphone']       = $client['phone_cc'].$client['phone'];
        $rp['country']      = $client['country'];
        $rp['emailMD5']     = md5($client['email']);
        $rp['txn_type']     = 'paypal'; /* payment gateway */
        $rp['license_key']  = ''; // your MaxMind license key
        $rp['requested_type']  = 'standard';   /* your request type preference */
        $fraudscore=25; /* your riskScore preference */


        $pdo = Box_Db::getPdo();
        $q="SELECT custom_9 FROM client WHERE id = :client_id LIMIT 1";
        $stmt = $pdo->prepare($q);
        $stmt->execute(array('client_id'=>$client['id']));
        $fraudtest = $stmt->fetchColumn();


        $pdo = Box_Db::getPdo();
        $q="SELECT custom_8 FROM client WHERE id = :client_id LIMIT 1";
        $stmt = $pdo->prepare($q);
        $stmt->execute(array('client_id'=>$client['id']));
        $mmchecks = $stmt->fetchColumn();


 If($fraudtest=='"'.'city'.'"')  {
throw new Payment_Exception('The City that you have entered does not exists. Please check spelling. ');
}
else if ($fraudtest=='"'.'fraud'.'"') {
throw new Payment_Exception('Your order was flagged as suspicious by MaxMind. '.'Please contact support.');
}
else if ($fraudtest=='"'.'freemail'.'"') {
throw new Payment_Exception('Orders using e-mails from a free e-mail providers are disabled. Please use another e-mail to place your order. ');
}
else If($fraudtest=='"'.'postcode'.'"')  {
throw new Payment_Exception('The Zip/Postcode that you have entered does not exists. Please check spelling. ');
}

else {

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

list($mm,$mmscore) = explode("=",$keyvaluepairs['43']);
$mmv[$mm] = $mmscore;

list($mmc,$mmcity) = explode("=",$keyvaluepairs['7']);
$mmct[$mmc] = $mmcity;

list($emmc,$mmmail) = explode("=",$keyvaluepairs['3']);
$emmct[$emmc] = $mmmail;

 If ($mmcity=='CITY_NOT_FOUND') {

$tt='city';
     $pdo = Box_Db::getPdo();
        $q="UPDATE client
            SET custom_9 = :value
            WHERE id = :client_id
            LIMIT 1";
        $stmt = $pdo->prepare($q);
        $stmt->execute(array('client_id'=>$client['id'], 'value'=>json_encode($tt)));

throw new Payment_Exception('The City that you have entered does not exists. Please check spelling. ');
}

else If ($mmcity=='POSTAL_CODE_NOT_FOUND') {

$tt='postcode';
     $pdo = Box_Db::getPdo();
        $q="UPDATE client
            SET custom_9 = :value
            WHERE id = :client_id
            LIMIT 1";
        $stmt = $pdo->prepare($q);
        $stmt->execute(array('client_id'=>$client['id'], 'value'=>json_encode($tt)));

throw new Payment_Exception('The Zip/Postcode that you have entered does not exists. Please check spelling. ');
}

/* disable orders with e-mails from a free e-mail providers */

/*
else If ($mmmail=='Yes') {

$tt='freemail';
     $pdo = Box_Db::getPdo();
        $q="UPDATE client
            SET custom_9 = :value
            WHERE id = :client_id
            LIMIT 1";
        $stmt = $pdo->prepare($q);
        $stmt->execute(array('client_id'=>$client['id'], 'value'=>json_encode($tt)));

throw new Payment_Exception('Orders using e-mails from a free e-mail providers are disabled. Please use another e-mail to place your order. ');
}
*/

else If (($mmscore>=$fraudscore)) {

$tt='fraud';
     $pdo = Box_Db::getPdo();
        $q="UPDATE client
            SET custom_9 = :value
            WHERE id = :client_id
            LIMIT 1";
        $stmt = $pdo->prepare($q);
        $stmt->execute(array('client_id'=>$client['id'], 'value'=>json_encode($tt)));

throw new Payment_Exception('Your order was flagged as suspicious by MaxMind. '.'Please contact support.');
}

}

    }

    public static function onBeforeClientSignUp(Box_Event $event)
    {
        $data = $event->getSubject();
        if(!isset($data['country']) || empty($data['country'])) {
            throw new Box_Exception('Please select your country');
        }
        if(!isset($data['city']) || empty($data['city'])) {
            throw new Box_Exception('Please enter your city');
        }
        if(!isset($data['phone_cc']) || empty($data['phone_cc'])) {
            throw new Box_Exception('Please enter your Phone Country Code');
        }
        if(!isset($data['phone']) || empty($data['phone'])) {
            throw new Box_Exception('Please enter your phone');
        }
        if(!isset($data['address_1']) || empty($data['address_1'])) {
            throw new Box_Exception('Please enter your address');
        }
        if(!isset($data['postcode']) || empty($data['postcode'])) {
            throw new Box_Exception('Please enter your Zip/Postal Code');
        }
    }


}