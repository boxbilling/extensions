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
            'description'     =>  'CCAvenue',
            'form'  => array(
                'id' => array('text', array(
                            'label' => 'Terminal ID',
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
            return 'https://www.';
        }
		return 'https://www.';
    }

    public function singlePayment(Payment_Invoice $invoice)
    {
		$data = array();
        return $data;
    }

    public function recurrentPayment(Payment_Invoice $invoice)
    {
        throw new Exception('Subscription not supported');
    }

    public function getTransaction($data, Payment_Invoice $invoice)
    {
        $ipn = $data['post'];
        
        $response = new Payment_Transaction();
        $response->setType(Payment_Transaction::TXTYPE_PAYMENT);
        $response->setId(1);
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
