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
	}
	
	public function getType()
    {
		return Payment_AdapterAbstract::TYPE_FORM;
	}
	
	public function getServiceUrl()
    {
		if ($this->testMode) {
			return 'https://authorize.payments-sandbox.amazon.com/pba/paypipeline';	
		}
		
		return 'https://authorize.payments.amazon.com/pba/paypipeline';
	}

    /**
     * @todo not finished
     * 
     * @param Payment_Invoice $invoice
     * @return <type>
     */
	public function singlePayment(Payment_Invoice $invoice)
    {
        throw new Exception('Amazon Payment gateway is under development.');
        
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
        $recurrenceInfo = $invoice->getSubscription();
        $amount = $invoice->getCurrency() . ' '. $invoice->getTotalWithTax();
        $description = $invoice->getTitle();

        $promotionAmount = 0;
        $processImmediate = true;
        $immediateReturn = true;
        $referenceId = $invoice->getId();
        $recurringStartDate = "";
        $recurringFrequency = $this->_getRecurringFrequency($recurrenceInfo); // '1 month'
        $subscriptionPeriod = ""; //'12 months';

        $formHiddenInputs['accessKey']          = $this->getParam('AWSAccessKeyId');
        $formHiddenInputs['amount']             = $amount;
        $formHiddenInputs['description']        = $description;
        $formHiddenInputs['recurringFrequency'] = $recurringFrequency;
        $formHiddenInputs['subscriptionPeriod'] = $subscriptionPeriod;
        $formHiddenInputs['recurringStartDate'] = $recurringStartDate;
        $formHiddenInputs['promotionAmount']    = $promotionAmount;
        $formHiddenInputs['referenceId']        = $referenceId;
        $formHiddenInputs['immediateReturn']    = true;
        $formHiddenInputs['ipnUrl']             = $this->getParam('notify_url');
        $formHiddenInputs['returnUrl']          = $this->getParam('return_url');
        $formHiddenInputs['abandonUrl']         = $this->getParam('cancel_url');
        $formHiddenInputs['processImmediate']   = $processImmediate;

        uksort($formHiddenInputs, "strnatcasecmp");
        
        $stringToSign = "";
        foreach ($formHiddenInputs as $formHiddenInputName => $formHiddenInputValue) {
            $stringToSign = $stringToSign . $formHiddenInputName . $formHiddenInputValue;
        }
        $formHiddenInputs['signature'] = $this->_getSignature($stringToSign);

        //throw new Exception(print_r($formHiddenInputs, 1));
        return $formHiddenInputs;
	}
    
    public function getInvoiceId($data)
    {
        $id = parent::getInvoiceId($data);
        if(!is_null($id)) {
            return $id;
        }
        
        return isset($data['post']['referenceId']) ? (int)$data['post']['referenceId'] : NULL;
    }
    
	public function getTransaction($data, Payment_Invoice $invoice)
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

	public static function getConfig()
    {
		return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  true,
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

    private function _getRecurringFrequency(Payment_Invoice_Subscription $recurrenceInfo)
    {
        switch ($recurrenceInfo->getCycle()) {
            case 'D':
                $t = 'days';
                break;

            case 'W':
                $t = 'week';
                break;

            case 'M':
                $t = 'month';
                break;

            case 'Y':
                $t = 'year';
                break;

            default:
                $t = 'month';
                break;
        }
        
        return $recurrenceInfo->getCycle() . ' ' . $t;
    }

    private function _getSignature($stringToSign)
    {
        $secretKey = $this->getParam('AWSSecretKey');
        $binary_hmac = supporter_amazon_create_hmac("sha1", trim($stringToSign), $secretKey, true);
        return base64_encode($binary_hmac);
    }
}

//from http://www.php.net/manual/en/function.hash-hmac.php#93440 to enable PHP4 support
function supporter_amazon_create_hmac($algo, $data, $key, $raw_output = false) {
    $algo = strtolower($algo);
    $pack = 'H'.strlen($algo('test'));
    $size = 64;
    $opad = str_repeat(chr(0x5C), $size);
    $ipad = str_repeat(chr(0x36), $size);

    if (strlen($key) > $size) {
        $key = str_pad(pack($pack, $algo($key)), $size, chr(0x00));
    } else {
        $key = str_pad($key, $size, chr(0x00));
    }

    for ($i = 0; $i < strlen($key) - 1; $i++) {
        $opad[$i] = $opad[$i] ^ $key[$i];
        $ipad[$i] = $ipad[$i] ^ $key[$i];
    }

    $output = $algo($opad.pack($pack, $algo($ipad.$data)));

    return ($raw_output) ? pack($pack, $output) : $output;
}