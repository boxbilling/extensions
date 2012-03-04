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
class Payment_Adapter_AmazonSimplePay extends Payment_AdapterAbstract
{
	public function init()
    {
		
		if (!extension_loaded('curl')) {
            throw new Payment_Exception('cURL extension is not enabled');
        }
        
        if (!class_exists('SimpleXMLElement')){
        	throw new Payment_Exception('SimpleXML extension is not enabled');
        }
        
		if (!$this->getParam('AWSAccessKeyId')) {
			throw new Payment_Exception('Payment gateway "Amazon Simple Pay" is not configured properly. Please update configuration parameter "AWS Access Key Id" at "Configuration -> Payments".');
		}
		
		if (!$this->getParam('AWSSecretKey')) {
			throw new Payment_Exception('Payment gateway "Amazon Simple Pay" is not configured properly. Please update configuration parameter "AWS Secret Key" at "Configuration -> Payments".');
		}
		
		//including Amazon library
		@include_once('Amazon/ButtonGenerationWithSignature/src/ButtonGenerator.php');
		@include_once('Amazon/IPNAndReturnURLValidation/src/SignatureUtilsForOutbound.php');
		if (!class_exists('ButtonGenerator') || !class_exists('SignatureUtilsForOutbound')) {
			throw new Payment_Exception('Amazon ASPStandar-PHP files are missing');
		}
	}
	
	public function getType()
    {
		return Payment_Adapter_Abstract::TYPE_FORM;
	}
	
	public function getServiceUrl()
    {
		if ($this->testMode) {
			return 'https://authorize.payments-sandbox.amazon.com/pba/paypipeline';	
		}
		
		return 'https://authorize.payments.amazon.com/pba/paypipeline';
	}

	public function singlePayment(Payment_Invoice $invoice)
    {		
		ob_start();
		ButtonGenerator::GenerateForm(
			$this->getParam('AWSAccessKeyId'), 
			$this->getParam('AWSSecretKey'), 
			$invoice->getCurrency() . ' ' . $invoice->getTotalWithTax(), 
			$invoice->getTitle(),
			$invoice->getId(), 
			0, 
			$this->getParam('return_url'), 
			$this->getParam('cancel_url'), 
			1, 
			$this->getParam('notify_url'),
			0,
			'HmacSHA256',
			'sandbox');
		
		$content = ob_get_contents();
		ob_end_clean();
		
		return $content;
	}

	public function recurrentPayment(Payment_Invoice $invoice)
    {
		throw new Payment_Exception('Not implemented');	
	}
    
    public function getInvoiceId($data)
    {
        $id = parent::getInvoiceId($data);
        if(!is_null($id)) {
            return $id;
        }
        
        return isset($data['post']['referenceId']) ? (int)$data['post']['referenceId'] : NULL;
    }
    
	public function ipn($data, Payment_Invoice $invoice)
    {
        $ipn = $data['post'];
        
		$transaction = new Payment_Transaction();
		
		if (isset($ipn['transactionAmount'])) {
			$money = explode(' ', $ipn['transactionAmount']);
			if (isset($money[0])) $transaction->setCurrency($money[0]);
			if (isset($money[1])) $transaction->setAmount(round($money[1], 2));
		} 
		
		if (isset($ipn['transactionId'])) {
			$transaction->setId($ipn['transactionId']);
		}
		
		if (isset($ipn['status']) && $ipn['status'] == 'PS') {
			$transaction->setStatus(Payment_Transaction::STATUS_COMPLETE);
		}
		
		if (isset($ipn['operation']) && $ipn['operation'] == 'pay') {
			$transaction->setType(Payment_Transaction::TXTYPE_PAYMENT);
		}

		return $transaction;
	}
    
    public function isIpnValid($data, Payment_Invoice $invoice)
    {
        $ipn = $data['post'];
		$validation = new SignatureUtilsForOutbound();
		return $validation->validateRequest($ipn, $this->getParam('notify_url'), 'POST');
    }

	public static function getConfig() {
		return array(
            'description'     =>  'Enter your Amazon Simple Pay details',
            'form'  => array(
                'AWSAccessKeyId' => array('text', array(
                            'label' => 'AWS Access Key Id', 
                            'description' => 'AWS Access Key Id', 
                            'validators'=>array('notempty'),
                    ),
                 ),
                 'AWSSecretKey' => array('text', array(
                 			'label' => 'AWS Secret Key',
                 			'description' => 'AWS Secret Key',
                 			'validators' => array('notempty'),
                 	),
                 ),
            ),
        );
	}

}