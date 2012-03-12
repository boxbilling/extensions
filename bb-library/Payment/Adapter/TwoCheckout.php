<?php
class Payment_Adapter_TwoCheckout extends Payment_AdapterAbstract
{
    public function init()
    {
        if(!$this->getParam('vendor_nr')) {
            throw new Payment_Exception('Payment gateway "2Checkout" is not configured properly. Please update configuration parameter "Vendor account number" at "Configuration -> Payments".');
        }

        if(!$this->getParam('secret')) {
            throw new Payment_Exception('Payment gateway "2Checkout" is not configured properly. Please update configuration parameter "Secret word" at "Configuration -> Payments".');
        }
    }
    
    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  true,
            'description'     =>  'Allows to start accepting payments by 2Checkout. You must enable INS notifications by logging on to your 2checkout account and navigating to <i>Notifications -> Settings</i> section. Set <i>\'Global URL\'</i> to BoxBilling Callback URL and click on <i>\'Enable All Notifications\'</i> and "Save settings".',
            'form'  => array(
                'vendor_nr' => array('text', array(
                            'label' => '2CO Account #', 
                            'description' => '2Checkout account number is number with which you login to 2CO account',
                    ),
                 ),
                'secret' => array('password', array(
                            'label' => 'Secret word', 
                            'description' => 'To set up the secret word please log in to your 2CO account, click on the “Account” tab, then click on “Site Management” subcategory. On the “Site Management” page you will enter the Secret Word in the field provided under Direct Return. After you have entered your Secret Word click the blue “Save Changes” button at the bottom of the page.',
                    ),
                 ),
                'single_page' => array('text', array(
                            'label' => '1 for Single page checkout - 0 for Multi-page checkout',
                            'description' => 'How will 2Checkout redirect page will look like', 
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
        if((bool)$this->getParam('single_page') === false) {
            return 'https://www.2checkout.com/checkout/purchase';
        }
		return 'https://www.2checkout.com/checkout/spurchase';
    }

    public function getInvoiceId($data)
    {
        $id = parent::getInvoiceId($data);
        if(!is_null($id)) {
            return $id;
        }
        
        return isset($data['post']['vendor_order_id']) ? (int)$data['post']['vendor_order_id'] : NULL;
    }

    /**
     * Return form params
     * @see http://www.2checkout.com/community/blog/category/knowledge-base/tech-support/3rd-party-carts/parameter-sets
     * @param Payment_Invoice $invoice
     */
    public function singlePayment(Payment_Invoice $invoice)
    {
        $b = $invoice->getBuyer();
        $full_name = $b->getFirstName().' '.$b->getLastName();

        //Used to specify an approved URL on-the-fly, but is limited to the same domain that is used for your 2Checkout account, otherwise it will fail. This parameter will over-ride any URL set on the Site Management page.
        $data['x_receipt_link_URL'] = $this->getParam('return_url');
        $data['return_url']         = $this->getParam('notify_url');

        $data['sid']                = $this->getParam('vendor_nr');
        $data['mode']				= '2CO';
        $data['total']              = $this->moneyFormat($invoice->getTotalWithTax());

        $data['fixed']              = 1;
        $data['lang']               = 'en';
        $data['skip_landing']       = 1;
        $data['id_type']            = 1;

        $data['merchant_order_id']  = $invoice->getId(); // will be returned as vendor_order_id in IPN

        $data['card_holder_name']   = $full_name;
        $data['phone']              = $b->getPhone();
        $data['phone_extension']    = '';
        $data['email']              = $b->getEmail();
        $data['street_address']     = $b->getAddress();
        $data['city']         = $b->getCity();
        $data['state']        = $b->getState();
        $data['zip']          = $b->getZip();
        $data['country']      = $b->getCountry();

        $data['ship_name']          = $full_name;
        $data['ship_steet_address'] = $b->getAddress();
        $data['ship_city']          = $b->getCity();
        $data['ship_state']         = $b->getState();
        $data['ship_zip']           = $b->getZip();
        $data['ship_country']       = $b->getCountry();

        $data['cart_order_id']      = $invoice->getNumber();

        foreach($invoice->getItems() as $i => $item) {
        	$data['li_' . $i . '_type']			= 'product';
        	$data['li_' . $i . '_tangible']		= 'N';
        	$data['li_' . $i . '_product_id']   = $item->getId();
        	$data['li_' . $i . '_name'] 		= $item->getTitle();
        	$data['li_' . $i . '_quantity']		= $item->getQuantity();
        	$data['li_' . $i . '_description']	= $item->getDescription();
            $data['li_' . $i . '_price']		= $item->getTotalWithTax();
        }

        if($this->testMode) {
            $data['demo'] = 'Y';
        }
        
        return $data;
    }

    /**
     * Perform recurent payment
     * @param Payment_Invoice $invoice
     * @see http://www.2checkout.com/blog/knowledge-base/merchants/tech-support/3rd-party-carts/parameter-sets/pass-through-product-parameter-set/
     */
    public function recurrentPayment(Payment_Invoice $invoice)
    {
    	$subs = $invoice->getSubscription();
    	$buyer = $invoice->getBuyer();
        
        $data['sid']				=	$this->getParam('vendor_nr');
        $data['mode']				=	'2CO';

        foreach($invoice->getItems() as $i => $item) {
        	$data['li_' . $i . '_type']			= 'product';
        	$data['li_' . $i . '_name'] 		= $item->getTitle();
        	$data['li_' . $i . '_quantity']		= $item->getQuantity();
        	$data['li_' . $i . '_tangible']		= 'N';
        	$data['li_' . $i . '_description']	= $item->getDescription();
        	$data['li_' . $i . '_recurrence']	= $subs->getCycle() . ' ' . ucfirst($subs->getUnit());
            $data['li_' . $i . '_price']		= $item->getTotalWithTax();
        }

        $data['merchant_order_id']  = $invoice->getId();
        $data['invoice_hash']       = $invoice->getId();
        $data['invoice_id']         = $invoice->getId();

        $data['fixed']              = 1;
        $data['lang']               = 'en';
        $data['skip_landing']       = 0;
        $data['id_type']            = 1;

        $data['x_receipt_link_URL'] = $this->getParam('return_url');

        $data['card_holder_name']   = $buyer->getFirstName(). ' '.$buyer->getLastName();
        $data['phone']              = $buyer->getPhone();
        $data['phone_extension']    = '';
        $data['email']              = $buyer->getEmail();
        $data['street_address']     = $buyer->getAddress();
        $data['city']         		= $buyer->getCity();
        $data['state']        		= $buyer->getState();
        $data['zip']          		= $buyer->getZip();
        $data['country']      		= $buyer->getCountry();
        $data['subscription']		= 1;

    	if($this->testMode) {
            $data['demo'] = 'Y';
        }

        return $data;
    }

    /**
     * Handle IPN and return response object
     * @todo
     * @return Payment_Transaction
     */
    public function getTransaction($data, Payment_Invoice $invoice)
    {
        $ipn = $data['post'];
        $tx = new Payment_Transaction();

        $tx->setId($ipn['sale_id']);
        $tx->setAmount($ipn['invoice_cust_amount']);
        $tx->setCurrency($ipn['cust_currency']);

        switch ($ipn['message_type']) {

            case 'ORDER_CREATED':
                $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);
                $tx->setStatus(Payment_Transaction::STATUS_COMPLETE);
                break;

            case 'REFUND_ISSUED':
                $tx->setType(Payment_Transaction::TXTYPE_REFUND);
                break;

            case 'RECURRING_INSTALLMENT_SUCCESS':
                $tx->setType(Payment_Transaction::TXTYPE_SUBSCR_CREATE);
                break;
            
            case 'RECURRING_STOPPED':
                $tx->setType(Payment_Transaction::TXTYPE_SUBSCR_CANCEL);
                break;

            case 'FRAUD_STATUS_CHANGED':
            case 'SHIP_STATUS_CHANGED':
            case 'INVOICE_STATUS_CHANGED':
            case 'RECURRING_INSTALLMENT_FAILED':
            case 'RECURRING_STOPPED':
            case 'RECURRING_COMPLETE':
            case 'RECURRING_RESTARTED':
            default:
                //@todo implement in future
                break;
        }
        
        return $tx;
    }

    /**
     * Check if Ipn is valid
     * @see https://www.2checkout.com/static/va/documentation/INS/INS_User_Guide_04_08_2009.pdf
     */
    public function isIpnValid($data, Payment_Invoice $invoice)
    {
        if($this->isInsValid($data, $invoice)) {
            return true;
        }

        if($this->isSaleValid($data, $invoice)) {
            return true;
        }
        
        return false;
    }

    private function isInsValid($data, Payment_Invoice $invoice)
    {
        $ipn = $data['post'];
        if(!isset($ipn["key"])) {
            return false;
        }
        
        $vendorNumber   = isset($ipn["vendor_number"]) ? $ipn["vendor_number"] : $this->getParam('vendor_nr');
        $orderNumber    = isset($ipn["order_number"]) ? $ipn["order_number"] : NULL;
        $orderTotal     = isset($ipn["total"]) ? $ipn["total"] : NULL;
        $secret         = $this->getParam('secret');

        // If demo mode, the order number must be forced to 1
        if(isset($ipn['demo']) && $ipn['demo'] == 'Y') {
            $orderNumber = "1";
        }

        // Calculate md5 hash as 2co formula: md5(secret_word + vendor_number + order_number + total)
        $key = strtoupper(md5($secret . $vendorNumber . $orderNumber . $orderTotal));

        // verify if the key is accurate
        return ($ipn["key"] === $key);
    }
    
    /**
     * Check if Ipn is valid
     * @see https://www.2checkout.com/static/va/documentation/INS/INS_User_Guide_04_08_2009.pdf
     */
    private function isSaleValid($data, Payment_Invoice $invoice)
    {
        $ipn = $data['post'];
        if(!isset($ipn["md5_hash"])) {
            return false;
        }
        
        $sale_id        = isset($ipn["sale_id"]) ? $ipn["sale_id"] : NULL;
        $invoice_id     = isset($ipn["invoice_id"]) ? $ipn["invoice_id"] : NULL;
        $vendor_id      = isset($ipn["vendor_id"]) ? $ipn["vendor_id"] : $this->getParam('vendor_nr');
        $secret         = $this->getParam('secret');

        $key = strtoupper(md5($sale_id.$vendor_id.$invoice_id.$secret));

        // verify if the key is accurate
        return ($ipn["md5_hash"] === $key);
    }
}
