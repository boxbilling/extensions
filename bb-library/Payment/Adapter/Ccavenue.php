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
 *
 *
 * Testing:
 * ===================
 * You can test the integration with the gateway by using
 * 4111 1111 1111 1111 as the credit card number
 * with any valid exp date and 123 for cvv for "failures".
 *
 * Or use a real credit card with a nominal amount (less than RS.10).
 * For either test, enter "SUB-MERCHANT TEST"
 * in the Instructions/Notes area on the CCAvenue payment page.
 */
class Payment_Adapter_Ccavenue extends Payment_AdapterAbstract
{
    public function init()
    {

    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  false,
            'description'     =>  'Similar to Paypal, the checkout button will redirect the customer to the CCAvenue site to login and pay. Once payment is completed, the page will redirect back to your site.',
            'form'  => array(
                'merchantid' => array('text', array(
                    'label' => 'CCAvenue Merchant ID: The Merchant Id to use for the CCAVENUE service',
                    ),
                ),
                'workingkey' => array('text', array(
                    'label' => 'CCAvenue Working Key: put in the 32 bit alphanumeric key. (Get this key by logging to your CCAvenue merchant account and visit the "Generate Working Key" section at the "Settings & Options" page.)',
                    ),
                ),
             ),
        );
    }

    public function getType()
    {
        return Payment_AdapterAbstract::TYPE_FORM;
    }

    public function getServiceUrl()
    {
        if($this->testMode) {
            return 'https://www.ccavenue.com/shopzone/cc_details.jsp';
        }
		return 'https://www.ccavenue.com/shopzone/cc_details.jsp';
    }

    /**
     * @see http://integrate-payment-gateway.blogspot.in/2012/01/ccavenue-payment-integration-php.html
     * @param Payment_Invoice $invoice
     * @return array
     */
    public function singlePayment(Payment_Invoice $invoice)
    {
        $buyer = $invoice->getBuyer();
        
        $Merchant_Id    = $this->getParam('merchantid');
        $WorkingKey     = $this->getParam('workingkey');
        $Amount         = $invoice->getTotalWithTax();
        $Order_Id       = $invoice->getId();
        $Redirect_Url   = $this->getParam('redirect_url');
        $Checksum       = getCheckSum($Merchant_Id,$Amount,$Order_Id ,$Redirect_Url,$WorkingKey);

        $data = array();
        $data['Merchant_Id']    = $Merchant_Id;
        $data['Amount']         = $Amount;
        $data['Order_Id']       = $Order_Id;
        $data['Redirect_Url']   = $Redirect_Url;
        $data['Checksum']       = $Checksum;
        $data['TxnType']        = 'A';
        $data['ActionID']       = 'TXN';

        $data['billing_cust_name']      = $buyer->getFirstName() . ' ' . $buyer->getLastName();
        $data['billing_cust_address']   = $buyer->getAddress();
        $data['billing_cust_country']   = $buyer->getCountry();
        $data['billing_cust_state']     = $buyer->getState();
        $data['billing_cust_city']      = $buyer->getCity();
        $data['billing_zip']            = $buyer->getZip();
        $data['billing_zip_code']       = $buyer->getZip();
        $data['billing_cust_tel']       = $buyer->getPhone();
        $data['billing_cust_email']     = $buyer->getEmail();
        $data['delivery_cust_name']     = $buyer->getFirstName() . ' ' . $buyer->getLastName();
        $data['delivery_cust_address']  = $buyer->getAddress();
        $data['delivery_cust_city']     = $buyer->getCity();
        $data['delivery_cust_country']  = $buyer->getCountry();
        $data['delivery_cust_state']    = $buyer->getState();
        $data['delivery_cust_tel']      = $buyer->getPhone();
        
        $data['delivery_cust_notes']    = $invoice->getTitle();

        return $data;
    }

    public function recurrentPayment(Payment_Invoice $invoice)
    {
        throw new Exception('Subscription not supported by CCAvenue payment gateway');
    }

    public function getTransaction($data, Payment_Invoice $invoice)
    {
        $ipn = $data['post'];
        
        //@todo
        $response = new Payment_Transaction();
        $response->setType(Payment_Transaction::TXTYPE_PAYMENT);
        $response->setId(uniqid());
        $response->setAmount($invoice->getTotalWithTax());
        $response->setCurrency($invoice->getCurrency());
        $response->setStatus(Payment_Transaction::STATUS_COMPLETE);
        return $response;
    }

    public function isIpnValid($data, Payment_Invoice $invoice)
    {
        $ipn = $data['post'];
        return true;
    }
}

 //creating a signature using the given details for security reasons
 function getchecksum($MerchantId,$Amount,$OrderId ,$URL,$WorkingKey)
 {
     $str ="$MerchantId|$OrderId|$Amount|$URL|$WorkingKey";
     $adler = 1;
     $adler = adler32($adler,$str);
     return $adler;
 }

 //functions
 function adler32($adler , $str)
 {
     $BASE = 65521 ;
     $s1 = $adler & 0xffff ;
     $s2 = ($adler >> 16) & 0xffff;
     for($i = 0 ; $i < strlen($str) ; $i++)
     {
         $s1 = ($s1 + Ord($str[$i])) % $BASE ;
         $s2 = ($s2 + $s1) % $BASE ;
     }

     return leftshift($s2 , 16) + $s1;
 }
 
 //leftshift function
 function leftshift($str , $num)
 {
     $str = DecBin($str);
     for( $i = 0 ; $i < (64 - strlen($str)) ; $i++)
     $str = "0".$str ;
     for($i = 0 ; $i < $num ; $i++)
     {
     $str = $str."0";
     $str = substr($str , 1 ) ;
     }
     return cdec($str) ;
 }
 
 //cdec function
 function cdec($num)
 {
     for ($n = 0 ; $n < strlen($num) ; $n++)
     {
     $temp = $num[$n] ;
     $dec = $dec + $temp*pow(2 , strlen($num) - $n - 1);
     }
     return $dec;
 }