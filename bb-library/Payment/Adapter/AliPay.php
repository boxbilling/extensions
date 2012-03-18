<?php
/**
 * BoxBilling
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
class Payment_Adapter_AliPay extends Payment_AdapterAbstract
{
    public function init()
    {
        if(!$this->getParam('partner')) {
            throw new Payment_Exception('Payment gateway "AliPay" is not configured properly. Please update configuration parameter "Partner ID" at "Configuration -> Payment gateways".');
        }
        
        if (!$this->getParam('security_code')) {
        	throw new Payment_Exception('Payment gateway "AliPay" is not configured properly. Please update configuration parameter "Security Code" at "Configuration -> Payment gateways".');
        }

        if (!$this->getParam('seller_email')) {
        	throw new Payment_Exception('Payment gateway "AliPay" is not configured properly. Please update configuration parameter "Seller email" at "Configuration -> Payment gateways".');
        }
        
        $this->_config['charset']       = 'utf-8';
    }
    
    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'    =>  true,
            'supports_subscriptions'        =>  false,
            'description'                   =>  'Clients will be redirected to Alipay to make payment.',
            'form'  => array(
                'partner' => array('text', array(
                            'label' => 'AliPay Partner ID. After signing contract online successfully, Alipay provides the partner id',
                    ),
                 ),
                 'security_code' => array('text', array(
                 			'label' => 'AliPay security code. After signing contract online successfully, Alipay provides the 32bits security code',
                 	),
                 ),
                'seller_email' => array('text', array(
                            'label' => 'AliPay seller email. Your paypal account',
                    ),
                 ),
            ),
        );
    }
    
    /**
     * Return payment gateway type
     * @return string
     */
    public function getType()
    {
        return Payment_AdapterAbstract::TYPE_FORM;
    }
    
    /**
     * Return payment gateway type
     * @return string
     */
    public function getServiceUrl()
    {
        if($this->testMode) {
            return 'https://www.alipay.com/cooperate/gateway.do';
        }
		return 'https://www.alipay.com/cooperate/gateway.do';
    }

    public function getInvoiceId($data)
    {
        $id = parent::getInvoiceId($data);
        if(is_null($id)) {
            $id = isset($data['post']['out_trade_no']) ? (int)$data['post']['out_trade_no'] : NULL;
        }
        return $id;
    }

	public function singlePayment(Payment_Invoice $invoice) 
	{
		$client = $invoice->getBuyer();

        $real_method = '2';
        switch ($real_method){
            case '0':
                $service = 'trade_create_by_buyer';
                break;
            case '1':
                $service = 'create_partner_trade_by_buyer';
                break;
            case '2':
                $service = 'create_direct_pay_by_user';
                break;
        }

        $parameter = array(
            'service'           => $service,
            'partner'           => $this->getParam('partner'),
            '_input_charset'    => $this->getParam('charset'),
            'notify_url'        => $this->getParam('notify_url'),
            'return_url'        => $this->getParam('return_url'),

            'subject'           => $invoice->getTitle(),
            'out_trade_no'      => $invoice->getId(),
            'price'             => $invoice->getTotalWithTax(),
            'quantity'          => 1,
            'payment_type'      => 1,
            
            'logistics_type'    => 'EXPRESS',
            'logistics_fee'     => 0,
            'logistics_payment' => 'BUYER_PAY_AFTER_RECEIVE',
            
            'seller_email'      => $this->getParam('seller_email'),
        );

        ksort($parameter);
        reset($parameter);
        $data = $parameter;
        $data['sign'] = $this->_generateSignature($parameter);
        $data['sign_type'] = 'MD5';

        return $data;
	}

	public function recurrentPayment(Payment_Invoice $invoice) 
	{
		throw new Payment_Exception('AliPay does not support recurrent payments');
	}

	public function getTransaction($data, Payment_Invoice $invoice) 
	{
		$ipn = $data['post'];
        
		$tx = new Payment_Transaction();
		$tx->setId($ipn['sign']);
		$tx->setAmount($ipn['total_fee']);
		$tx->setCurrency($invoice->getCurrency());
        $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);
        
        switch ($ipn['trade_status']) {
            case 'TRADE_SUCCESS':
            case 'TRADE_FINISHED':
            //case 'WAIT_SELLER_SEND_GOODS':
                $tx->setStatus(Payment_Transaction::STATUS_COMPLETE);
                break;

            default:
                throw new Payment_Exception('Unknown AliPay IPN status :'.$ipn['trade_status']);
                break;
        }
        
		return $tx;
	}

    public function isIpnValid($data, Payment_Invoice $invoice)
    {
        $ipn = $data['post'];

        ksort($ipn);
        reset($ipn);

        $sign = '';
        foreach ($ipn AS $key=>$val)
        {
            if ($key != 'sign' && $key != 'sign_type' && $key != 'code')
            {
                $sign .= "$key=$val&";
            }
        }

        $sign = substr($sign, 0, -1) . $this->getParam('security_code');
        return (md5($sign) == $ipn['sign']);
    }

    private function _generateSignature(array $parameter)
    {
        $sign  = '';
        foreach ($parameter AS $key => $val)
        {
            $sign  .= "$key=$val&";
        }
        $sign  = substr($sign, 0, -1) . $this->getParam('security_code');
        return md5($sign);
    }
}