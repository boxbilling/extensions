<?php

class Payment_Adapter_tpay extends Payment_AdapterAbstract
{

    private $config = array();
    private $url = 'https://secure.tpay.com';
    const REQ = 'required';
    const TYPE = 'type';
    const STRING = 'string';
    const FLOAT = 'float';
    const VALIDATION = 'validation';
    const OPTIONS = 'options';
    const REMOTE = 'REMOTE_ADDR';
    const CRC = 'tr_crc';
    const TRSTATUS = 'tr_status';
    const MD5SUM = 'md5sum';
    const ERROR = 'tr_error';
    const PAYSTATUS = 'paymentStatus';
    const TRID = 'tr_id';
    const TRAMOUNT = 'tr_amount';
    const SELLERID = 'seller_id';
    const SECURITYCODE = 'security_code';
    const DESCRIPTION = 'description';
    const LABEL = 'label';
    const VALIDATORS = 'validators';
    const LANG = 'jezyk';
    const NOTEMPTY = 'notempty';
    const INVOICEID = 'invoice_id';
    const AMOUNT = 'amount';
    const STRINGREQUIRED = array(
        self::REQ        => true,
        self::TYPE       => self::STRING,
        self::VALIDATION => array(self::STRING),
    );
    const FLOATREQUIRED = array(
        self::REQ        => true,
        self::TYPE       => self::FLOAT,
        self::VALIDATION => array(self::FLOAT),
    );
    private $panelPaymentResponseFields = array(

        /*
         * The transaction ID assigned by the system Tpay
         */
        self::TRID     => self::STRINGREQUIRED,
        /*
         * Date of transaction.
         */
        'tr_date'      => self::STRINGREQUIRED,
        /*
         * The secondary parameter to the transaction identification.
         */
        self::CRC      => self::STRINGREQUIRED,
        /*
         * Transaction amount.
         */
        self::TRAMOUNT => self::FLOATREQUIRED,
        /*
         * The amount paid for the transaction.
         * Note: Depending on the settings, the amount paid can be different than transactions
         * eg. When the customer does overpayment.
         */
        'tr_paid'      => self::FLOATREQUIRED,
        /*
         * Description of the transaction.
         */
        'tr_desc'      => self::STRINGREQUIRED,
        /*
         * Transaction status: TRUE in the case of the correct result or FALSE in the case of an error.
         * Note: Depending on the settings, the transaction may be correct status,
         * even if the amount paid is different from the amount of the transaction!
         * Eg. If the Seller accepts the overpayment or underpayment threshold is set.
         */
        self::TRSTATUS => array(
            self::REQ        => true,
            self::TYPE       => self::STRING,
            self::VALIDATION => array(self::OPTIONS),
            self::OPTIONS    => array(0, 1, true, false, 'TRUE', 'FALSE'),
        ),
        /*
         * Transaction error status.
         * Could have the following values:
         * - none
         * - overpay
         * - surcharge
         */
        self::ERROR    => array(
            self::REQ        => true,
            self::TYPE       => self::STRING,
            self::VALIDATION => array(self::OPTIONS),
            self::OPTIONS    => array('none', 'overpay', 'surcharge'),
        ),
        /*
         * Customer email address.
         */
        'tr_email'     => array(
            self::REQ        => true,
            self::TYPE       => self::STRING,
            self::VALIDATION => array('email_list'),
        ),
        /*
         * The checksum verifies the data sent to the payee.
         * It is built according to the following scheme using the MD5 hash function:
         * MD5(id + tr_id + tr_amount + tr_crc + security code)
         */
        self::MD5SUM   => array(
            self::REQ        => true,
            self::TYPE       => self::STRING,
            self::VALIDATION => array(self::STRING, 'maxlength_32', 'minlength_32'),
        ),
        /*
         * Transaction marker indicates whether the transaction was executed in test mode:
         * 1 – in test mode
         * 0 – in normal mode
         */
        'test_mode'    => array(
            self::REQ        => false,
            self::TYPE       => 'int',
            self::VALIDATION => array(self::OPTIONS),
            self::OPTIONS    => array(0, 1),
        ),
        /*
         * The parameter is sent only when you use a payment channel or MasterPass or V.me.
         * Could have the following values: „masterpass” or „vme”
         */
        'wallet'       => array(
            self::REQ  => false,
            self::TYPE => self::STRING,
        ),
    );
    protected $di;

    private function setTxData($id, $ipn, $tx)
    {
        $txData = array(
            'id'     => $id,
            'status' => 'pending',
        );

        if (empty($tx['txn_id']) && isset($ipn[static::CRC])) {
            $txData['txn_id'] = $ipn[static::CRC];
        }

        if (empty($tx[static::AMOUNT]) && isset($ipn[static::TRAMOUNT])) {
            $txData[static::AMOUNT] = $ipn[static::TRAMOUNT];
        }
        if (!empty($tx[static::AMOUNT]) && $ipn[static::TRAMOUNT] != $tx[static::AMOUNT]) {
            throw new Payment_Exception("Transaction amounts different");
        }
        if (empty($tx['currency'])) {
            $txData['currency'] = 'PLN';
        }
        return $txData;
    }

    private function checkIfTpayServer()
    {
        $secureIP = array(
            '195.149.229.109',
            '148.251.96.163',
            '178.32.201.77',
            '46.248.167.59',
            '46.29.19.106'
        );
        if (!filter_input(INPUT_SERVER, static::REMOTE)
            || !in_array(filter_input(INPUT_SERVER, static::REMOTE), $secureIP)
        ) {
            throw new Payment_Exception("Invalid server IP or empty POST");
        }

        echo 'TRUE';
        return true;
    }

    private function checkPostErrors()
    {
        $ready = array();
        foreach ($this->panelPaymentResponseFields as $fieldName => $field) {
            if ($this->post($fieldName, static::STRING) === false) {
                if ($field[static::REQ] === true) {
                    throw new Payment_Exception("no required parameter " . $fieldName);
                }
            } else {
                $val = $this->post($fieldName, static::STRING);
                switch ($field[static::TYPE]) {
                    case 'string':
                        $val = (string)$val;
                        break;
                    case 'int':
                        $val = (int)$val;
                        break;
                    case 'float':
                        $val = (float)$val;
                        break;
                    default:
                        throw new Payment_Exception("unknown field type in getResponse - field name= %s '. $fieldName");
                }
                $ready[$fieldName] = $val;
            }
        }

    }

    private function post($name, $type)
    {
        if (!filter_input(INPUT_POST, $name)) {
            return false;
        }
        $val = filter_input(INPUT_POST, $name);

        if ($type === 'int') {
            $val = (int)$val;
        } elseif ($type === static::FLOAT) {
            $val = (float)$val;
        } elseif ($type === static::STRING) {
            $val = (string)$val;
        } else {
            throw new Payment_Exception("variable type not supported");
        }

        return $val;
    }

    private function isDataValid($ipn)
    {
        $this->checkIfTpayServer();
        $this->checkPostErrors();
        $this->checkDataValid($ipn);

        return true;
    }

    private function checkDataValid($ipn)
    {
        $sellerId = $this->config[static::SELLERID];
        $secCode = $this->config[static::SECURITYCODE];
        $localMd5sum = md5($sellerId . $ipn[static::TRID] . $ipn[static::TRAMOUNT] . $ipn[static::CRC] . $secCode);
        if (!is_string($ipn[static::MD5SUM])
            || strlen($ipn[static::MD5SUM]) !== 32
            || $ipn[static::MD5SUM] !== $localMd5sum
        ) {
            throw new Payment_Exception('Invalid md5sum ' . $sellerId . $ipn[static::TRID] .
                $ipn['tr_amount'] . $ipn['tr_crc'] . $secCode);
        }
        if ((int)$ipn[static::CRC] === false) {
            throw new Payment_Exception("Invalid order ID");
        }
        if ($ipn['tr_status'] !== 'TRUE') {
            throw new Payment_Exception("Transaction completed with error");
        }


    }

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function init()
    {
        if (!$this->getParam(static::SELLERID) || !$this->getParam(static::SECURITYCODE)) {
            throw new Payment_Exception('tpay.com is not configured, go to "Configuration -> Payments".');
        }

        if (!$this->getParam(static::LANG)) {
            throw new Payment_Exception('tpay.com is not configured, go to "Configuration -> Payments".');
        }
    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments' => true,
            'supports_subscriptions'     => false,
            static::DESCRIPTION          => 'Clients will be redirected to tpay.com to make payment.',
            'form'                       => array(
                static::SELLERID     => array('text', array(
                    static::LABEL       => 'Merchant ID',
                    static::DESCRIPTION => '',
                    static::VALIDATORS  => array(static::NOTEMPTY),
                ),
                ),
                static::SECURITYCODE => array('text', array(
                    static::LABEL       => 'Security code',
                    static::DESCRIPTION => 'Security code from tpay.com merchant panel.',
                    static::VALIDATORS  => array(static::NOTEMPTY),
                ),
                ),
                static::LANG         => array('text', array(
                    static::LABEL       => 'Gateway language',
                    static::DESCRIPTION => 'Default payment gateway language (PL, EN, DE supported).',
                    static::VALIDATORS  => array(static::NOTEMPTY),
                ),
                ),
            ),
        );
    }

    /**
     * Return payment gateway type
     * @return string
     */

    public function getHtml($apiAdmin, $invoiceId, $subscription)
    {
        $invoice = $apiAdmin->invoice_get(array('id' => $invoiceId));
        $buyer = $invoice['buyer'];
        $sellerId = $this->config[static::SELLERID];
        $secCode = $this->config[static::SECURITYCODE];
        $amount = $invoice['total'];
        $invId = $invoice['nr'];
        $params = array(
            'id'           => $sellerId,
            'kwota'        => $amount,
            'opis'         => 'Payment for order id: ' . $invId,
            'crc'          => $invId,
            'md5sum'       => md5($sellerId . $amount . $invId . $secCode),
            'wyn_url'      => $this->config['notify_url'],
            'pow_url'      => $this->config['return_url'],
            'pow_url_blad' => $this->config['cancel_url'],
            'email'        => $buyer['email'],
            'imie'         => $buyer['first_name'],
            'nazwisko'     => $buyer['last_name'],
            'adres'        => $buyer['address'],
            'telefon'      => $buyer['phone'],
            'miasto'       => $buyer['city'],
            'kod'          => $buyer['zip'],
            'kraj'         => $buyer['country'],
            static::LANG   => $this->config[static::LANG],
        );

        $html = '
            <form action="' . $this->getServiceUrl() . '" method=POST>
                ';

        foreach ($params as $key => $value) {
            $html .= '<input type=hidden name="' . $key . '"  value="' . $value . '">
					';
        }

        return $html . '
                            <input type=SUBMIT value="' . __('Pay now') .
        '" name=SUBMIT class="bb-button bb-button-submit bb-button-big">
        ';

    }

    /**
     * Return payment gateway type
     * @return string
     */
    public function getServiceUrl()
    {
        return $this->url;
    }

    public function recurrentPayment(Payment_Invoice $invoice)
    {
        // FUTURE

    }

    public function isIpnValid($data, Payment_Invoice $invoice)
    {
        return true;
    }

    public function processTransaction($apiAdmin, $id, $data, $gatewayId)
    {
        $ipn = $data['post'];
        $tx = $apiAdmin->invoice_transaction_get(['id' => $id]);
        $this->isDataValid($ipn);
        if ($this->isIpnDuplicate($ipn)) {
            throw new Payment_Exception("IPN is duplicate");
        }

        $apiAdmin->invoice_transaction_update(array('id' => $id, 'type' => 'tpay.com payment'));

        $invoiceId = null;
        if (isset($tx[static::INVOICEID])) {
            $invoiceId = $tx[static::INVOICEID];
        } elseif (isset($ipn[static::CRC])) {
            $invoiceId = $ipn[static::CRC];
            $apiAdmin->invoice_transaction_update(array('id' => $id, static::INVOICEID => $invoiceId));
        } else {
            throw new Payment_Exception('Invoice id could not be determined for this transaction');
        }

        $invoice = $apiAdmin->invoice_get(array('id' => $invoiceId));
        $clientId = $invoice['client']['id'];
        $txData = $this->setTxData($id, $ipn, $tx);

        $apiAdmin->invoice_transaction_update($txData);

        $bd = [
            'id'                => $clientId,
            static::AMOUNT      => $ipn["tr_paid"],
            static::DESCRIPTION => 'tpay.com transaction ' . $ipn[static::TRID] . ' for order: ' . $invoiceId,
            'type'              => 'tpay.com payment',
            'rel_id'            => $ipn[static::CRC]
        ];


        $apiAdmin->client_balance_add_funds($bd);

        $txData['txn_status'] = 'complete';
        $txData['status'] = 'complete';
        $apiAdmin->invoice_transaction_update($txData);

        $apiAdmin->invoice_batch_pay_with_credits(array('client_id' => $clientId));
    }

    public function isIpnDuplicate(array $ipn)
    {
        $sql = 'SELECT id
                FROM transaction
                WHERE txn_id = :transaction_id
                  AND amount = :transaction_amount
                LIMIT 2';

        $bindings = array(
            ':transaction_id'     => $ipn[static::CRC],
            ':transaction_amount' => $ipn[static::TRAMOUNT],
        );

        $rows = $this->di['db']->getAll($sql, $bindings);
        if (count($rows) > 1) {
            return true;
        }

        return false;
    }

}
