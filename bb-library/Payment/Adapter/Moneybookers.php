<?php
class Payment_Adapter_Moneybookers extends Payment_AdapterAbstract
{
    public function init()
    {
        if(!$this->getParam('email')) {
            throw new Payment_Exception('Payment gateway "Moneybookers" is not configured properly. Please update configuration parameter "Moneybookers Email address" at "Configuration -> Payments".');
        }
        
        if (!$this->getParam('secretWord')) {
        	throw new Payment_Exception('Payment gateway "Moneybookers" is not configured properly. Please update configuration parameter "Secret word" at "Configuration -> Payments".');
        }
    }
    
    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  false,
            'description'     =>  'Clients will be redirected to Moneybookers.com to make payment. Note that Moneybookers.com supports credit card payments.',
            'form'  => array(
                'email' => array('text', array(
                            'label' => 'Moneybookers Email address', 
                            'description' => 'Moneybookers Email address', 
                            'validators'=>array('EmailAddress'),
                    ),
                 ),
                 'secretWord' => array('text', array(
                 			'label' => 'Secret word',
                 			'description' => 'Secret word',
                 			'validators' => array('notempty'),
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
    	//Please update below URLS to newer domain taht is skrill.com.
        if($this->testMode) {
            return 'http://www.moneybookers.com/app/test_payment.pl';
        }
		return 'https://www.moneybookers.com/app/payment.pl';
    }

	public function singlePayment(Payment_Invoice $invoice) {
		$c = $invoice->getBuyer();
		$params = array(
			'pay_to_email'			=>	$this->getParam('email'),
			'transaction_id'		=>	$invoice->getNumber(),
			'return_url'			=>	$this->getParam('return_url'),
			'cancel_url'			=>	$this->getParam('cancel_url'),
			'status_url'			=>	$this->getParam('notify_url'),
			'merchant_fields'		=>	'invoice_id',
			'invoice_id'			=>	$invoice->getNumber(),
			'pay_from_email'		=>	$c->getEmail(),
			'firstname'				=>	$c->getFirstname(),
			'lastname'				=>	$c->getLastname(),
			'address'				=>	$c->getAddress(),
			'phone_number'			=>	$c->getPhone(),
			'postal_code'			=>	$c->getZip(),
			'city'					=>	$c->getCity(),
			'state'					=>	$c->getState(),
			'country'				=>	$c->getCountry(),
			'amount'				=>	$invoice->getTotal(),
			'currency'				=>	$invoice->getCurrency()
		);
		
		return $params;
	}

	public function recurrentPayment(Payment_Invoice $invoice) {
		// TODO Auto-generated method stub
		
	}

	public function getTransaction($data, Payment_Invoice $invoice) {
		$r = $data['post'];
		$tr = new Payment_Transaction();
		$tr->setAmount($r['mb_amount'])
		   ->setCurrency($r['currency']);

		if($r['md5sig'] == $this->_generateMD5($r)) {
			switch ($r['status']) {
				case '2': 	$tr->setStatus(Payment_Transaction::STATUS_COMPLETE);	break;
				case '0': 	$tr->setStatus(Payment_Transaction::STATUS_PENDING);		break;
			}
			$tr->setIsValid(true);
		}
		
		return $tr;
	}
	
	private function _generateMD5($r) {
		return strtoupper(
			MD5(
				$r['merchant_id'] .
				$r['transaction_id'] .
				strtoupper(
					MD5($this->getParam('secretWord'))
				) .
				$r['mb_amount'] .
				$r['mb_currency'] .
				$r['status']
			)
		);
	}
}
