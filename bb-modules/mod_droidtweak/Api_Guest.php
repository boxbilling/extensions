<?php
/**
 * Example BoxBilling module
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
class Box_Mod_Droidtweak_Api_Guest extends Api_Abstract
{
    /**
     * Store order
     */
    public function order($data)
    {
        if(!isset($data['first_name']) || empty($data['first_name'])) {
            throw new Exception('Your first name is required');
        }
        
        if(!isset($data['first_name']) || empty($data['last_name'])) {
            throw new Exception('Your last name is required');
        }
        
        if(!isset($data['email']) || empty($data['email'])) {
            throw new Exception('Your email is required');
        }
        
        if(!isset($data['market_url']) || empty($data['market_url'])) {
            throw new Exception('Market URL was not provided');
        }
        
        if(!isset($data['product_id']) || empty($data['product_id'])) {
            throw new Exception('Please select service');
        }
        
        if(!isset($data['content'])) {
            $data['content'] = '';
        }
        
        $mod = new Box_Mod('droidtweak');
        $config = $mod->getConfig();
        
        $data['content'] .= PHP_EOL.PHP_EOL.$config['review_text'];
        
        $api = $this->getApiAdmin();
        $api_guest = $this->getApiGuest();
        $currency = $api_guest->cart_get_currency();
        $product = $api->product_get(array('id'=>$data['product_id']));
        
        try {
            $client = $api->client_get(array('email'=>$data['email']));
            $client_id = $client['id'];
        } catch (Exception $e) {
            $client_data = array(
                'first_name'    =>  $data['first_name'],
                'last_name'     =>  $data['last_name'],
                'email'         =>  $data['email'],
                'currency'      =>  $currency['code'],
            );
            $client_id = $api->client_create($client_data);
        }
        
        $data['status'] = 'Pending Payment';
        Box_Db::getRb();
        $bean = R::dispense('extension_meta');
        $bean->extension = 'mod_droidtweak';
        $bean->client_id = $client_id;
        $bean->meta_key = 'order';
        $bean->meta_value = json_encode($data);
        $bean->created_at = date('c');
        $bean->updated_at = date('c');
        R::store($bean);
        
        $event_params = array(
            'meta_order'    => $bean->id,
        );
        
        $idata = array(
            'client_id'     => $client_id,
            'items'      => array(
                array(
                    'title'     => $product['title'],
                    'unit'      => 'service',
                    'price'     => $product['pricing']['once']['price'],
                    'type'      => 'hook_call',
                    'task'      => 'onAfterDroidTweakPayment',
                    'rel_id'    => json_encode($event_params),
                ),
            ),
        );
        $invoice_id = $api->invoice_prepare($idata);
        $api->invoice_approve(array('id'=>$invoice_id));
        $invoice = $api->invoice_get(array('id'=>$invoice_id));
        
        $this->_log('New service was ordered');
        
        return array(
            //'id'        =>  $invoice['id'],
            'hash'          => $invoice['hash'],
            'gateway_id'    => $config['gateway'],
        );
    }
}