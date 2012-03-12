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
class Payment_Adapter_Mellat extends Payment_AdapterAbstract
{
    public function init()
    {
        if(!extension_loaded('soap')) {
            throw new Payment_Exception('Soap extension required for Mellat payment gateway');
        }
        
        if(!$this->getParam('terminalId')) {
            throw new Payment_Exception('Payment gateway "Mellat" is not configured properly. Please update configuration parameters at "Configuration -> Payments".');
        }
        
        if(!$this->getParam('userName')) {
            throw new Payment_Exception('Payment gateway "Mellat" is not configured properly. Please update configuration parameters at "Configuration -> Payments".');
        }
        if(!$this->getParam('userPassword')) {
            throw new Payment_Exception('Payment gateway "Mellat" is not configured properly. Please update configuration parameters at "Configuration -> Payments".');
        }
    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  false,
            'description'     =>  'Mellat Bank itegration',
            'form'  => array(
                'terminalId' => array('text', array(
                            'label' => 'Terminal ID',
                    ),
                 ),
                'userName' => array('text', array(
                            'label' => 'Username',
                    ),
                 ),
                'userPassword' => array('password', array(
                            'label' => 'Password',
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
            return 'https://pgwtest.bpm.bankmellat.ir/pgwchannel/startpay.mellat';
        }
		return 'https://pgw.bpm.bankmellat.ir/pgwchannel/startpay.mellat';
    }

    /**
     * Init call to webservice or return form params
     * @param Payment_Invoice $invoice
     */
    public function singlePayment(Payment_Invoice $invoice)
    {
        $parameters = array(
            'terminalId'    => $this->getParam('terminalId'),
            'userName'      => $this->getParam('userName'),
            'userPassword'  => $this->getParam('userPassword'),
            'orderId'       => $invoice->getId() . rand(1,9999),
            'amount'        => $invoice->getTotalWithTax(),
            'localDate'     => date('Ynd'),
            'localTime'     => date('His'),
            'additionalData'=> $invoice->getTitle(),
            'callBackUrl'   => $this->getParam('redirect_url'),
            'payerId'       => "0",
        );

        $client = $this->_getSoapClient();
        $result = $client->bpPayRequest($parameters);
        $res = explode (',', $result->return);
        $code = isset($res[0]) ? $res[0] : NULL;
        $refid = isset($res[1]) ? $res[1] : NULL;
        if($code !== '0') {
            throw new Exception('Mellat error requesting bpPayRequest: '.$code);
        }
        $data = array(
            'RefId' => $refid,
        );
        return $data;
    }

    /**
     * Perform recurent payment
     */
    public function recurrentPayment(Payment_Invoice $invoice)
    {
        throw new Exception('Subscription not supported');
    }

    /**
     * Handle IPN and return response object
     * @return Payment_Transaction
     */
    public function getTransaction($data, Payment_Invoice $invoice)
    {
        $ipn = $data['post'];
        
        $client = $this->_getSoapClient();
        
        $Pay_Status             = 'FAIL';
        
        $terminalId             = $this->getParam('terminalId');
        $userName               = $this->getParam('userName');
        $userPassword           = $this->getParam('userPassword');
        $refId                  = $ipn['RefId'];
        $resCode                = $ipn['ResCode'];
        $orderId                = $ipn['SaleOrderId'];
        $verifySaleOrderId      = $ipn['SaleOrderId'];
        $verifySaleReferenceId  = $ipn['SaleReferenceId'];

        $parameters = array(
			'terminalId'        => $terminalId,
			'userName'          => $userName,
			'userPassword'      => $userPassword,
			'orderId'           => $orderId,
			'saleOrderId'       => $verifySaleOrderId,
			'saleReferenceId'   => $verifySaleReferenceId
        );
        
        error_log('Parameters: '.print_r($parameters, 1));
        
		$result = $client->bpVerifyRequest($parameters);
        $VerifyAnswer = $result->return;
        error_log('Verify answer:' . $VerifyAnswer);
        
        if($VerifyAnswer == '0'){
            // Call the SOAP method
            $result = $client->bpSettleRequest($parameters);
            $SetlleAnswer = $result->return;
            error_log('Settle answer:' . $SetlleAnswer);
            
            if ($SetlleAnswer == '0'){
                $Pay_Status = 'OK'; 
            }
        }

        if ($VerifyAnswer <> '0' AND $VerifyAnswer != '' ) {
            $result = $client->bpInquiryRequest($parameters);
            $InquiryAnswer = $result->return;
            error_log('Inquiry Answer:' . $InquiryAnswer);
            
            if ($InquiryAnswer == '0'){
                    // Call the SOAP method
                    $result = $client->bpSettleRequest($parameters);
                    $SetlleAnswer = $result->return;
                    error_log('Second Settle Answer:' . $InquiryAnswer);
                    if ($SetlleAnswer == '0'){
                        $Pay_Status = 'OK'; 
                    }
            } else {
                // Call the SOAP method
                $result = $client->bpReversalRequest($parameters);
                $ReversalAnswer = $result->return;
                error_log('Reversal request Answer:' . $ReversalAnswer);
            }
        }

        if ($Pay_Status != 'OK' ){
            throw new Payment_Exception('Sale verification failed: '.$VerifyAnswer);
        }
        
        $response = new Payment_Transaction();
        $response->setType(Payment_Transaction::TXTYPE_PAYMENT);
        $response->setId($refId);
        $response->setAmount($invoice->getTotalWithTax());
        $response->setCurrency($invoice->getCurrency());
        $response->setStatus(Payment_Transaction::STATUS_COMPLETE);
        return $response;
    }

    /**
     * Check if Ipn is valid
     */
    public function isIpnValid($data, Payment_Invoice $invoice)
    {
        $ipn = $data['post'];
        return true;
    }
    
    private function _getSoapClient()
    {
        if($this->testMode) {
            $wsdl = 'https://pgwstest.bpm.bankmellat.ir/pgwchannel/services/pgw?wsdl';
        } else {
            $wsdl = 'https://pgws.bpm.bankmellat.ir/pgwchannel/services/pgw?wsdl';
        }
        
        $options = array(
            'uri'   =>  'http://interfaces.core.sw.bps.com/',
            'trace'      => true,
            'exceptions' => true,
        );
        return new SoapClient($wsdl, $options);
    }
}
