<?php
class Payment_Adapter_LibertyReserve extends Payment_AdapterAbstract
{
    public function init()
    {
        if (!function_exists('hash')) {
        	throw new Payment_Exception('Liberty reserve payments needs php hash function to work (http://www.php.net/manual/en/function.hash.php)');
        }

        if(!$this->getParam('accountNumber')) {
            throw new Payment_Exception('Payment gateway "Liberty Reserve" is not configured properly. Please update configuration parameter "Liberty Reserve account number" at "Configuration -> Payments".');
        }
        
        if (!$this->getParam('securityWord')) {
        	throw new Payment_Exception('Payment gateway "Liberty Reserve" is not configured properly. Please update configuration parameter "Store Security Word" at "Configuration -> Payments".');
        }
    }
    
    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  false,
            'description'     =>  'In your account go to <i>Merchant Tools &gt; create new store</i> and enter Store name and Security Word',		//@TODO
            'form'  => array(
                'accountNumber' => array('text', array(
                            'label' => 'Liberty Reserve account number', 
                            'description' => 'Liberty Reserve account number', 
                            'validators'=>array('notempty'),
                    ),
                 ),
                'securityWord' => array('text', array(
                            'label' => 'Store Security Word', 
                            'description' => 'Store Security Word', 
                            'validators'=>array('notempty'),
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
		return 'https://sci.libertyreserve.com/';
    }

	public function singlePayment(Payment_Invoice $invoice) {
		$params = array(
			'lr_acc'				=>	$this->getParam('accountNumber'),	//Merchant's account number 
			'lr_amnt'				=>	$invoice->getTotal(),				//Amount to be transferred to Merchant's account. This field is optional, but if it exists the Buyer will not be able to change payment amount.
			'lr_currency'			=>	'LR' . $invoice->getCurrency(),		//Currency type preferred. This field is optional, and if omitted will default to LRUSD.
			'lr_merchant_ref'		=>	$invoice->getNumber(),				//Additional identifying factor that can be set by the Merchant. This information is stored in the Liberty Reserve transaction database.This field is optional.
			'lr_success_url'		=>	$this->getParam('return_url'),		//URL address of payment successful page at the Merchant's web site. This field is not required. Also it can be specified in SCI store settings in your account. If omitted and SCI works in simple mode, SCI server will return Buyer to Merchant's checkout page. 
			'lr_fail_url'			=>	$this->getParam('cancel_url'),		//URL address of payment failed page at the Merchant's web site. This field is not required. Also it can be specified in SCI store settings in your account. If omitted and SCI works in simple mode, SCI server will return Buyer to Merchant's checkout page. 
			'lr_status_url'			=>	$this->getParam('notify_url'),		//One of the following: a) URL address of payment status page at the Merchant's web site. In this case URL value must be prefixed by "https:" or "http:". b) E-mail address to send a successful payment notification via e-mail. E-mail body contains data of payment status form. In this case URL value must be prefixed by "mailto:". This field is not required. It can be specified in SCI store settings in your account. If omitted and SCI works in simple mode, SCI server will not perform status request.
			'lr_status_url_method' 	=>	'POST',								//payment status form data transmit HTTP method. This field is not required. It can be specified in SCI store settings in your account.
		);
		
		return $params;
	}


	public function recurrentPayment(Payment_Invoice $invoice) {
		throw new Payment_Exception('Not implemented yet');
	}


	public function getTransaction($data, Payment_Invoice $invoice) {
		$r = $data['post'];
		$tr = new Payment_Transaction();
		$tr->setAmount($r['lr_amnt'])
		   ->setCurrency(str_replace('LR', '', $r['lr_currency']));
		
		if ($r['lr_encrypted'] == $this->_getHash1($r)) {
			$tr->setStatus(Payment_Transaction::STATUS_COMPLETE)
			   ->setIsValid(true);
		}
		
		return $tr;
	}

	private function _getHash1($r)
	{
		return strtoupper(
			hash('sha256',
				$r['lr_paidto'] . ':' .
				$r['lr_paidby'] . ':' .
				$r['lr_store'] . ':' .
				$r['lr_amnt'] . ':' .
				$r['lr_transfer'] . ':' .
				$r['lr_currency'] . ':' .
				$this->getParam('secretWord')
			)
		);	
	}
}