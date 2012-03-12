<?php
class Payment_Adapter_Ideal extends Payment_AdapterAbstract
{
	private $_time;
	
    public function init()
    {
        if(!$this->getParam('hashKey')) {
            throw new Payment_Exception('Payment gateway "iDEAL" is not configured properly. Please update configuration parameter "Hashkey" at "Configuration -> Payments".');
        }
        
        if (!$this->getParam('acquirerUrl')) {
        	throw new Payment_Exception('Payment gateway "iDEAL" is not configured properly. Please update configuration parameter "Address of the iDEAL acquiring server" at "Configuration -> Payments".');
        }
        
        if (!$this->getParam('merchantId')) {
        	throw new Payment_Exception('Payment gateway "iDEAL" is not configured properly. Please update configuration parameter "MerchantID" at "Configuration -> Payments".');
        }
        
        $this->_time = time();
    }
    
    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  false,
            'description'     =>  'Description', //@TODO
            'form'  => array(
                'hashKey' => array('text', array(
                            'label' => 'Hashkey', 
                            'description' => 'The hashkey as in the iDEAL dashboard', 
                            'validators'=>array('notempty'),
                    ),
                 ),
                 'acquirerUrl' => array('text', array(
                 			'label' => 'Address of the iDEAL acquiring server',
                 			'description' => 'Address of the iDEAL acquiring server',
                 			'validators' => array('notempty'),
                 	),
                 ),
                 'merchantId' => array('text', array(
                 			'label' => 'MerchantID',
                 			'description' => 'MerchantID',
                 			'validators' => array('notempty'),
                 	),
                 ),
                 'subId' => array('text', array(
                 			'label' => 'SubID',
                 			'description' => 'SubID',
                 			'validators' => array(),
                 			'default' => 0,
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

	public function getServiceUrl() 
	{
		if ($this->testMode) {
			return 'https://www.ideal-simulator.nl/lite/';
		}
		
		return $this->getParam('acquirerUrl');
	}

	public function singlePayment(Payment_Invoice $invoice) 
	{
		$params = array(
			'merchantID'	=>	$this->getParam('merchantId'),
			'SubID'			=>	(int)$this->getParam('subId'),
			'amount'		=>	(int)($invoice->getTotal() * 100),
			'purchaseID'	=>	$invoice->getNumber(),
			//'language'		=>	'en',
			'currency'		=>	$invoice->getCurrency(),
			'hash'			=>	$this->_getHash($invoice),
			'paymentType'	=>	'iDEAL',
			'validUntil'	=>	$this->_getValidUntil(),
			'urlSuccess'	=>	$this->getParam('return_url'),
			'urlCancel'		=>	$this->getParam('cancel_url'),
			'urlError'		=>	$this->getParam('return_url'),
			'urlService'	=>	$this->getParam('notify_url'),
		);
		
		$i = 1;
		foreach ($invoice->getItems() as $item) {
			$params['itemNumber' . $i] = $item->getId();
			$params['itemDescription' . $i] = $item->getDescription();
			$params['itemQuantity' . $i] = $item->getQuantity();
			$params['itemPrice' . $i] = (int)($item->getPrice() * 100);
			$i++;
		}
	
		return $params;
	}

	public function recurrentPayment(Payment_Invoice $invoice) 
	{
		throw new Payment_Exception('Not implemented yet');
	}

	public function getTransaction($data, Payment_Invoice $invoice) {
		throw new Payment_Exception('Not implemented yet');
	}

	private function _getHash(Payment_Invoice $invoice)
	{
  		$shastring = $this->getParam('hashKey') .
  					 $this->getParam('merchantId') .
  					 $this->getParam('subId') .
  					 $invoice->getNumber() .
  					 'ideal' .
  					 $this->_getValidUntil();

		$i = 1;
		foreach ($invoice->getItems() as $item) {
			$shastring .= $i . $item->getDescription() . $item->getQuantity() . (int)($item->getPrice() * 100);
			$i++;
		}
		 
		//Replace forbidden chars
  		$shastring = str_replace(" ","",$shastring);
  		$shastring = str_replace("\t","",$shastring);
  		$shastring = str_replace("\n","",$shastring);
  		$shastring = str_replace("&amp;","&",$shastring);
  		$shastring = str_replace("&lt;","<",$shastring);
  		$shastring = str_replace("&gt;","gt-teken",$shastring);
  		$shastring = str_replace("&quot;","\"",$shastring);

  		return sha1($shastring);
	}
	
	private function _getValidUntil()
	{
		$validUntil = $this->_time + 900; // 15 minutes from now to complete the payment
  		$validUntil = date("Y-m-dTH:i:s.0000", $validUntil);
  		$validUntil = str_replace("CEST", "T", $validUntil);
  		$validUntil = $validUntil . "Z";
  		
  		return $validUntil;
	}
}