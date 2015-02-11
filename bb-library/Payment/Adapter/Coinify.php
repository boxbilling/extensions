<?php
/**
 * coinify
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
class Payment_Adapter_coinify
{
    private $config = array();
    
    public function __construct($config)
    {
        $this->config = $config;
        
        if(!function_exists('curl_exec')) {
            throw new Exception('PHP Curl extension must be enabled in order to use Coinify gateway');
        }
        
        if(!$this->config['coinify_api']) {
            throw new Exception('Payment gateway "Coinify" is not configured properly. Please update configuration parameter "Coinify Invoice API Key" at "Configuration -> Payments".');
        }

        if(!$this->config['coinify_secret']) {
            throw new Exception('Payment gateway "Coinify" is not configured properly. Please update configuration parameter "Coinify Secret" at "Configuration -> Payments".');
        }
    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  false,
            'description'     =>  'Enter your Coinify invoice API Key to start accepting payments by Bitcoin.',
            'form'  => array(
                'coinify_api' => array('password', array(
                            'label' => 'Coinify invoice API Key for Invoice'
                    )
                 ),
				'coinify_secret' => array('password', array(
					'label' => 'Coinify Secret for callback'
					)
                 )
            )
        );
    }

    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoice = $api_admin->invoice_get(array('id' => $invoice_id));
        $buyer = $invoice['buyer'];
        
        $p = array(
            ':id'=>sprintf('%05s', $invoice['nr']), 
            ':serie'=>$invoice['serie'], 
            ':title'=>$invoice['lines'][0]['title']
        );
        $title = __('Payment for invoice :serie:id [:title]', $p);
        $number = $invoice['nr'];

		$form = '';

		if (!isset($_GET['status']))
		{
			$ch = curl_init();
			curl_setopt_array($ch, array(
			CURLOPT_URL => 'https://coinify.com/api/v1/invoice',
			CURLOPT_USERPWD => $this->config['coinify_api'],
			CURLOPT_POSTFIELDS => 'price=' . $this->moneyFormat($invoice['total'], $invoice['currency']) . '&currency=' . $invoice['currency'] . '&item=' . $title . ' - ' . $number . '&custom=' . json_encode(array('invoice_id' => $invoice_id, 'returnurl' => rawurlencode($this->config['return_url']), 'cancelurl' => rawurlencode($this->config['cancel_url']))),
			CURLOPT_SSL_VERIFYHOST	=> 0,
			CURLOPT_SSL_VERIFYPEER	=> 0,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC));
			$rurl = curl_exec($ch);
			curl_close($ch);

			if (strlen($rurl) == 0)
			{
				return 'error';
			}

			$form  = '';
			$form .= '<form name="payment_form" action="' . $rurl . '" method="POST">' . PHP_EOL;
			$form .=  '<input class="bb-button bb-button-submit" type="submit" value="Pay with Coinify" id="payment_button"/>'. PHP_EOL;
			$form .=  '</form>' . PHP_EOL . PHP_EOL;

			if(isset($this->config['auto_redirect']) && $this->config['auto_redirect']) {
				$form .= sprintf('<h2>%s</h2>', __('Redirecting to coinify'));
				$form .= "<script type='text/javascript'>$(document).ready(function(){ document.getElementById('payment_button').style.display = 'none'; document.forms['payment_form'].submit();});</script>";
			}
		}

        return $form;
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
		$coinify = $_POST;
		$hash = hash('sha512', $coinify['transaction']['hash'] . $this->config['coinify_secret']);

		header('HTTP/1.1 200 OK');

		if ($coinify['hash'] == $hash && $coinify['status'] == 1)
		{
			$invoice_id = intval($coinify["custom"]["invoice_id"]);
	
			$tx = $api_admin->invoice_transaction_get(array('id'=> $id));
			
			if (!$tx['invoice_id'])
			{
				$api_admin->invoice_transaction_update(array('id' => $id, 'invoice_id' => $invoice_id));
			}
			
			if(!$tx['amount'])
			{
				$api_admin->invoice_transaction_update(array('id' => $id, 'amount' => $coinify['fiat']["amount"]));
			}
			
			$invoice = $api_admin->invoice_get(array('id' => $invoice_id));
			$client_id = $invoice['client']['id'];

			$bd = array(
				'id'            =>  $client_id,
				'amount'        =>  $coinify['fiat']["amount"],
				'description'   =>  'coinify transaction ' . $coinify["transaction"]["hash"],
				'type'          =>  'coinify',
				'rel_id'        =>  $coinify['transaction']["hash"],
			);
			$api_admin->client_balance_add_funds($bd);
			$api_admin->invoice_batch_pay_with_credits(array('client_id' => $client_id));
	 
			$d = array(
				'id'        => $id, 
				'error'     => '',
				'error_code'=> '',
				'status'    => 'processed',
				'updated_at'=> date('c'),
			);
			$api_admin->invoice_transaction_update($d);
		}
    }

    private function moneyFormat($amount, $currency)
    {
        //HUF currency do not accept decimal values
        if($currency == 'HUF') {
            return number_format($amount, 0);
        }
        return number_format($amount, 2, '.', '');
    }
}