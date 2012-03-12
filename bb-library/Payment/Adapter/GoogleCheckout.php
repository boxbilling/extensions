<?php
/**
 * 
 * Google Checkout
 * 
 * @author Mindaugas
 *
 */
class Payment_Adapter_GoogleCheckout extends Payment_AdapterAbstract
{
    public function init()
    {
        if (!function_exists('simplexml_load_string')) {
        	throw new Payment_Exception('SimpleXML extension not enabled');
        }

        if(!$this->getParam('merchantId')) {
            throw new Payment_Exception('Payment gateway "Google checkout" is not configured properly. Please update configuration parameter "Google merchant ID" at "Configuration -> Payments".');
        }
        
        if(!$this->getParam('merchantKey')) {
            throw new Payment_Exception('Payment gateway "Google checkout" is not configured properly. Please update configuration parameter "Google merchant key" at "Configuration -> Payments".');
        }
    }
    
    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  false,
            'description'     =>  'To use Google Checkout in a live environment, you must have an SSL certificate.<br /><br />'.
        						  'In Google Checkout account you need to go to <i>Settings &gt; Preferences &gt; Order processing preferences</i> '.
        						  'and<br /> select the option <i>Automatically authorize and <strong>charge</strong> the buyer\'s credit card.</i> '.
        						  '<br /><br />Also, in <i>Settings &gt; Integration</i> And in <i>API callback URL</i> enter Callback url '.
        						  'and select Notification as XML option.',
            'form'  => array(
                'merchantId' 	=> array('text', array(
                            'label' 		=> 'Google merchant ID', 
                            'description' 	=> 'Google merchant ID', 
                            'validators'	=> array('notempty'),
                    ),
                 ),
                 'merchantKey'	=> array('password', array(
                 			'label'			=> 'Google merchant key',
                 			'description'	=> 'Google merchant key',
                 			'validators'	=> array('notempty'),
                 	),
                 ),
            ),
        );
    }

	public function getType() {
		return Payment_AdapterAbstract::TYPE_API;
	}

	public function getServiceUrl() {
		if ($this->testMode) {
			return 'https://sandbox.google.com/checkout/api/checkout/v2/merchantCheckout/Merchant/' . $this->_config['merchantId'];
		}
		
		return 'https://checkout.google.com/api/checkout/v2/merchantCheckout/Merchant/' . $this->_config['merchantId'];
	}

	/**
	 * @see http://code.google.com/apis/checkout/developer/Google_Checkout_XML_API_Tag_Reference.html
	 */
	public function singlePayment(Payment_Invoice $invoice)
    {
		//replace in text nodes
		$what = array('&', '<', '>');
		$to = array('&#x26;', '&#x3c;', '&#x3e;');
		
		$xml = new DOMDocument('1.0', 'UTF-8');
		
		$root = $xml->createElement('checkout-shopping-cart');
		$root->setAttribute('xmlns', 'http://checkout.google.com/schema/2');
		$xml->appendChild($root);
		
		$shoppingCart = $xml->createElement('shopping-cart');
		$root->appendChild($shoppingCart);
		
		$items = $xml->createElement('items');
		$shoppingCart->appendChild($items);
		
		foreach($invoice->getItems() as $i) {
			$item = $xml->createElement('item');
			$itemName = $xml->createElement('item-name', str_replace($what, $to, $i->getTitle()));
			$itemDescription = $xml->createElement('item-description', str_replace($what, $to, $i->getDescription()));
			$unitPrice = $xml->createElement('unit-price', $i->getPrice());
			$unitPrice->setAttribute('currency', $invoice->getCurrency());
			$quantity = $xml->createElement('quantity', $i->getQuantity());
			$digitalContent = $xml->createElement('digital-content');
			
			$description = $xml->createElement('description', str_replace($what, $to, '<a href="' . $this->getParam('return_url') . '">Click here to return to site</a>'));
			$digitalContent->appendChild($description);
			
			
			$item->appendChild($itemName);
			$item->appendChild($itemDescription);
			$item->appendChild($unitPrice);
			$item->appendChild($quantity);
			$item->appendChild($digitalContent);
				 
			$items->appendChild($item);
		}
		
		$checkoutFlowSupport = $xml->createElement('checkout-flow-support');
		$root->appendChild($checkoutFlowSupport);
		
		$merchantCheckoutSupport = $xml->createElement('merchant-checkout-flow-support');
		$checkoutFlowSupport->appendChild($merchantCheckoutSupport);
		
		$continueShoppingUrl = $xml->createElement('continue-shopping-url', str_replace($what, $to, $this->getParam('continue_shopping_url')));
		$merchantCheckoutSupport->appendChild($continueShoppingUrl);
		
		$merchantPrivateDdata = $xml->createElement('merchant-private-data');
		$shoppingCart->appendChild($merchantPrivateDdata);
		
		$invoiceId = $xml->createElement('invoice', $invoice->getNumber());
		$merchantPrivateDdata->appendChild($invoiceId);
		
		$str = $xml->saveXML();
		
		$response = $this->_makeRequest($str);
		
		if (isset($response->{'redirect-url'})) {
			return urldecode($response->{'redirect-url'});
		} else {
			throw new Payment_Exception('Connection to Google Checkout servers failed');
		}
		
		return false;
	}

	public function recurrentPayment(Payment_Invoice $invoice) {
		// TODO Auto-generated method stub
		
	}

	public function getTransaction($data, Payment_Invoice $invoice) {
		$request = $data['http_raw_post_data'];
        
		try {
			$xml = simplexml_load_string($request);
		} catch (Exception $e) {
			throw new Payment_Exception('Can\'t parse xml');
		}

		//adds pending transaction with pending status
		if (isset($xml->{'fulfillment-order-state'}) && $xml->{'fulfillment-order-state'} == 'NEW' &&
			isset($xml->{'shopping-cart'}->{'merchant-private-data'}->invoice)) {

			return $this->_processNewOrderNotification($xml);
		}
		
		//changes transaction status to complete
		if (isset($xml->{'total-charge-amount'}) && isset($xml->{'google-order-number'})) {
			return $this->_finishTransaction($xml, $invoice);
		}
		
	}
	
    private function _makeRequest($xml) 
    {
    	$headers = array(
    		'Authorization: Basic ' . base64_encode($this->_config['merchantId'] . ':' . $this->_config['merchantKey']),
    		'Content-Type: application/xml;charset=UTF-8',
    		'Accept: application/xml;charset=UTF-8' 
    	);

    	$ch = curl_init ();
    	curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
    	curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);
    	curl_setopt ($ch, CURLOPT_HTTPHEADER, $headers);
    	curl_setopt ($ch, CURLOPT_URL, $this->getServiceUrl());
    	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
    	curl_setopt ($ch, CURLOPT_TIMEOUT, 60);
    	curl_setopt ($ch, CURLOPT_POSTFIELDS, $xml);
    	//debug
    	//curl_setopt($ch, CURLOPT_VERBOSE, true);
    	
		$result = curl_exec($ch);
		
		if (curl_errno ($ch)) {
			throw new Payment_Exception('cURL error: ' . curl_errno ($ch) . ' - ' . curl_error ($ch));
		}
		
		return $this->_parseResponse($result);
    }
    
    private function _parseResponse($result)
    {
    	try {
            $xml = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);
        } catch (Exception $e) {
            throw new Payment_Exception('simpleXmlException: '.$e->getMessage());
        }
        
        if (isset($xml->{'error-message'})) {
        	throw new Payment_Exception($xml->{'error-message'});
        }
        
        return $xml;
    }
    
    private function _getServerType() 
    {
    	if ($this->testMode) {
    		return 'sandbox';
    	} else {
    		return 'production';
    	}
    }
    
    /**
     * 
     * Creates and return transaction with pending status
     * We need to do so because only in new transaction notification
     * Google returns invoice number. In all other notifications only
     * Google order number is returned
     * 
     * @param SimpleXmlElement $xml
     * @return Payment_Transaction
     */
    private function _processNewOrderNotification(SimpleXMLElement $xml) {
    	$tr = new Payment_Transaction();
		$tr->setIsValid(true)
		   ->setStatus(Payment_Transaction::STATUS_PENDING);

		if (isset($xml->{'order-total'})) {
			$tr->setAmount((string)$xml->{'order-total'});
			$tr->setCurrency((string)$xml->{'order-total'}['currency']);
		}

		if (isset($xml->{'google-order-number'})) {
			$tr->setId((string)$xml->{'google-order-number'});
		}

		return $tr;
    }
    
    /**
     * 
     * Finds client invoice transaction by Google order id
     * @param SimpleXMLElement $xml
     * @throws Payment_Exception
     * @return Payment_Transaction
     */
    private function _finishTransaction(SimpleXMLElement $xml, $invoice = null)
    {
		$t = new Payment_Transaction();
		$t->setAmount($xml->{'total-charge-amount'})
		  ->setCurrency($xml->{'total-charge-amount'}['currency'])
		  ->setStatus(Payment_Transaction::STATUS_COMPLETE)
//		  ->setId($tr->id) //@todo
		  ->setIsValid(true);
		
		return $t;
    }
}