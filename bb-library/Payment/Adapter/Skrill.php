<?php


class Payment_Adapter_Skrill implements \Box\InjectionAwareInterface
{
    /**
     * @var \Box_Di
     */
    protected $di;

    /**
     * @param Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    private $config = array();

    public function __construct($config)
    {
        $this->config = $config;

        if(!function_exists('curl_exec')) {
            throw new Exception('PHP Curl extension must be enabled in order to use Skrill gateway');
        }

        if(!$this->config['email']) {
            throw new Exception('Payment gateway "Skrill" is not configured properly. Please update configuration parameter "Skrill Email address" at "Configuration -> Payments".');
        }
    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  true,
            'description'     =>  'Enter your Skrill email to start accepting payments by Skrill.',
            'form'  => array(
                'email' => array('text', array(
                    'label' => 'Skrill email address for payments',
                    'validators'=>array('EmailAddress'),
                ),
                ),
                'secret_word' => array('text', array(
                    'label' => 'Skrill account secrect word for payment validation. Enter in the Settings > Developer Settings section in your Skrill account',
                    'validators' => array('notempty'),
                ),
                ),
            ),
        );
    }

    public function getInvoiceTitle(array $invoice)
    {
        $p = array(
            ':id'=>sprintf('%05s', $invoice['nr']),
            ':serie'=>$invoice['serie'],
            ':title'=>$invoice['lines'][0]['title']
        );
        return __('Payment for invoice :serie:id [:title]', $p);
    }

    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoice = $api_admin->invoice_get(array('id'=>$invoice_id));
        $buyer = $invoice['buyer'];
        $data = array();

        // Merchant Details
        $data['pay_to_email']       = $this->config['email'];
        $data['return_url']         = $this->config['return_url'];
        $data['cancel_url']         = $this->config['cancel_url'];
        $data['status_url']         = $this->config['notify_url'];
        $data['language']           = $invoice['currency'];

        $data['currency']           = $invoice['currency'];

        $data['detail1_description']= 'title';
        $data['detail1_text']       = $this->getInvoiceTitle($invoice);


        if($subscription) {
            $subs = $invoice['subscription'];

            // Customer Details
            $data['pay_from_email']  	= $buyer['email'];
            $data['firstname']			= $buyer['first_name'];
            $data['lastname']			= $buyer['last_name'];
            $data['address']			= $buyer['address'];
            $data['phone_number']		= $buyer['phone'];
            $data['postal_code']		= $buyer['zip'];
            $data['city']				= $buyer['city'];
            $data['state']			    = $buyer['state'];
            $data['country']			= $buyer['country'];

            // Recurring Billing
            $data['rec_status_url'] 	= $this->config['notify_url'];
            $data['rec_amount'] 		= $invoice['total'];
            $data['rec_period'] 		= $subs['cycle'];
            if($subs['unit']=='M') {
                $data['rec_cycle'] = 'month';
            } elseif($subs['unit']=='Y') {
                $data['rec_cycle'] = 'year';
            } elseif($subs['unit']=='W') {
                $data['rec_cycle'] = 'day';
                $data['rec_period'] = 7*$subs['cycle'];
            }
        } else {
            // Payment Details
            $data['amount']             = $invoice['total'];
        }

        $url = 'https://pay.skrill.com';
        $sid = $this->getSecureRedirectionSessionid($url, $data);
        $url = $url.'?sid='.$sid;
        return $this->_generateForm($url, $data);
    }

    public function getSecureRedirectionSessionid($url, $data = array())
    {
        $url = $url . '?prepare_only=1';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch,CURLOPT_POST, count($data));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        if (curl_errno($ch)){
            return false;
        }

        curl_close($ch);
        preg_match('/^Set-Cookie:\s*([^;]*)/mi', $response, $m);
        return $m[1];
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
        if(APPLICATION_ENV != 'testing' && !$this->_isIpnValid($data)) {
            throw new Exception('IPN is not valid');
        }

        $ipn = $this->di['array_get']($data, 'post', array());
        $get = $this->di['array_get']($data, 'get', array());

        $invoice = $api_admin->invoice_get(array('id'=>$this->di['array_get']($get, 'bb_invoice_id')));
        $client_id = $invoice['client']['id'];

        $txn_type = 'payment';
        if ($this->di['array_get']($get, 'rec_payment') =='cancelled')
        {
            $txn_type = 'subscription_cancel';
        }

        // Determine BB txn_status
        switch ($this->di['array_get']($ipn, 'status')){
            case '2': // MB processed
                $txn_status='complete';
                break;

            case '0': // MB pending
                $txn_status='pending';
                break;

            case '-2': // MB failed
            case '-1': // MB cancelled
            case '-3': // MB chargeback
            default:
                $txn_status='unknown';
                break;
        }

        $tx_data = array(
            'id'            =>  $id,
            'invoice_id'    =>  $this->di['array_get']($get, 'bb_invoice_id'),
            'txn_status'    =>  $txn_status,
            'txn_id'        =>  $this->di['array_get']($ipn, 'mb_transaction_id'),
            'amount'        =>  $this->di['array_get']($ipn, 'mb_amount'),
            'currency'      =>  $this->di['array_get']($ipn, 'mb_currency'),
            'type'          =>  $txn_type,
            'status'        =>  $txn_status,
            'updated_at'    =>  date('Y-m-d H:i:s'),
        );
        $api_admin->invoice_transaction_update($tx_data);


        switch ($txn_type) {

            case 'payment':
                if ($txn_status='complete') {
                    $bd = array(
                        'id'            =>  $client_id,
                        'amount'        =>  $this->di['array_get']($ipn, 'mb_amount'),
                        'description'   =>  'Skrill transaction '.$ipn['mb_transaction_id'],
                        'type'          =>  'Skrill',
                        'rel_id'        =>  $this->di['array_get']($ipn, 'mb_transaction_id'),
                    );
                    $api_admin->client_balance_add_funds($bd);
                    if($this->di['array_get']($get, 'bb_invoice_id')) {
                        $api_admin->invoice_pay_with_credits(array('id'=>$this->di['array_get']($get, 'bb_invoice_id')));
                    }
                    $api_admin->invoice_batch_pay_with_credits(array('client_id'=>$client_id));
                }
                break;

            case 'subscription_cancel':

                break;

        }
    }

    private function _isIpnValid($data)
    {
        $hash  = '';
        $hash .= $data['post']['merchant_id'];
        $hash .= $data['post']['transaction_id'];
        $hash .= strtoupper(md5($this->config['secret_word']));
        $hash .= $data['post']['mb_amount'];
        $hash .= $data['post']['mb_currency'];
        $hash .= $data['post']['status'];
        $md5hash = strtoupper(md5($hash));
        if ($md5hash == $data['post']['md5sig']) {
            return true;
        }
        return false;
    }

    private function _generateForm($url, $data, $method = 'post')
    {
        $form  = '';
        $form .= '<form name="payment_form" action="'.$url.'" method="'.$method.'">' . PHP_EOL;
        foreach($data as $key => $value) {
            $form .= sprintf('<input type="hidden" name="%s" value="%s" />', $key, $value) . PHP_EOL;
        }
        $form .=  '<input class="bb-button bb-button-submit" type="submit" value="Pay with Skrill" id="payment_button"/>'. PHP_EOL;
        $form .=  '</form>' . PHP_EOL . PHP_EOL;

        if(isset($this->config['auto_redirect']) && $this->config['auto_redirect']) {
            $form .= sprintf('<h2>%s</h2>', __('Redirecting to Skrill.com'));
            $form .= "<script type='text/javascript'>$(document).ready(function(){    document.getElementById('payment_button').style.display = 'none';    document.forms['payment_form'].submit();});</script>";
        }

        return $form;
    }
}