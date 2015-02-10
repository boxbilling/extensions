<?php
class Registrar_Adapter_Realtimeregister extends Registrar_AdapterAbstract
{
    public $config = array(
        'user'   => null,
        'password' => null,
    );

    public function __construct($options)
    {
        if (!extension_loaded('curl')) {
            throw new Registrar_Exception('CURL extension is not enabled');
        }
        
        if(isset($options['user']) && !empty($options['user'])) {
            $this->config['user'] = $options['user'];
            unset($options['user']);
        } else {
            throw new Registrar_Exception('Domain registrar "RealtimeRegister" is not configured properly. Please update configuration parameter "RealtimeRegister Username" at "Configuration -> Domain registration".');
        }

        if(isset($options['password']) && !empty($options['password'])) {
            $this->config['password'] = $options['password'];
            unset($options['password']);
        } else {
            throw new Registrar_Exception('Domain registrar "RealtimeRegister" is not configured properly. Please update configuration parameter "RealtimeRegister Password" at "Configuration -> Domain registration".');
        }
    }

    public static function getConfig()
    {
        return array(
            'label' => 'Manages domains on RealtimeRegister via API',
            'form'  => array(
                'user' => array('text', array(
                            'label' => 'RealtimeRegister Username',
                            'description'=>'RealtimeRegister Username',
                    ),
                 ),
                'password' => array('password', array(
                            'label' => 'RealtimeRegister Password',
                            'description'=>'RealtimeRegister Password',
                            'renderPassword' => true,
                    ),
                 ),
            ),
        );
    }

    public function getTlds()
    {
        return array(
            '.nl', '.com', '.net', '.org', '.info',  '.eu',
            '.co.uk', '.me', '.be', '.biz','.de', '.dk',
            '.ch',  '.es', '.com.es', '.org.es', '.nom.es', 
            '.eu', '.li', '.me', '.nl', '.se', '.me.uk', 
            '.co.uk', '.org.uk', '.ltd.uk', '.plc.uk', '.net.uk', '.ae', 
            '.am', '.cc', '.fm', '.in', '.co.in', '.firm.in', '.gen.in', 
            '.ind.in', '.net.in', '.org.in', '.mobi', '.name', '.nu', '.tel', 
            '.to', '.tv',
        );
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $result = $this->_request('domains/' . $domain->getName() . '/check');
        
        return $result->{$domain->getName()}->avail == 1;
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        // Send empty array to remove previous nameservers
        $ns_list = array();
        $params = array(
            'ns' => $ns_list,
        );
        $this->_request('domains/' . $domain->getName() . '/update', $params);
        
        foreach ($domain->getNameservers() as $ns) {
            $ns_list[] = array(
                'host' => $ns->getHost(),
            );
        }
        
        $params = array(
            'ns' => $ns_list,
        );
        $this->_request('domains/' . $domain->getName() . '/update', $params);
        
        return true;
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();
        
        $result = $this->_request('domains/' . $domain->getName() . '/info');

        $params = array(
            'email' => $c->getEmail(),
            'name' => $c->getName(),
            'street' => array(
                $c->getAddress1(),
                $c->getAddress2(),
                $c->getAddress3(),
            ),
            'city' => $c->getCity(),
            'sp' => $c->getState(),
            'pc' => $c->getZip(),
            'cc' => $c->getCountry(),
            'voice' => '+' . $c->getTelCc() . '.' . $c->getTel(),
            'fax' => ($c->getFax() ? '+' . $c->getFaxCc() . '.' . $c->getFax() : ''),
        );
        $this->_request('contacts/' . $result->registrant . '/update', $params);
        
        return true;
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        $result = $this->_request('domains/' . $domain->getName() . '/info');
        
        $params = array(
            'auth' => $domain->getEpp(),
            'request_type' => 'transfer',
            'registrant' => $result->registrant,
            'admin' => $result->admin,
            'tech' => $result->tech,
            'billing' => $result->billing,
        );
        $this->_request('domains/' . $domain->getname() . '/transfer', $params);
        
        return true;
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
        $result = $this->_request('domains/' . $domain->getName() . '/info');
        $contact = $this->_request('contacts/' . $result->registrant . '/info');
        
        $tel = explode('.', $contact->voice);
        
        $c = new Registrar_Domain_Contact();
        $c->setName($contact->name)
          ->setEmail($contact->email)
          ->setTel($tel[1])
          ->setTelCc($tel[0])
          ->setCity($contact->city)
          ->setCountry($contact->cc)
          ->setZip($contact->pc);
        
        for ($i = 0; $i < 2; $i++) {
            if (isset($contact->street[$i])) {
                $c->{'setAddress' . ($i + 1)}($contact->street[$i]);
            }
        }

        // Add nameservers
        $ns_list = array();
        foreach ($result->ns as $ns)
        {
            $n = new Registrar_Domain_Nameserver();
            $n->setHost($ns->host);
            $ns_list[] = $n;
        }
        
        $domain->setNameservers($ns_list);
        $domain->setExpirationTime($result->exDate);
        $domain->setRegistrationTime($result->crDate);
        $domain->setEpp((isset($result->pw) ? $result->pw : ''));
        $domain->setContactRegistrar($c);

        return $domain;
    }

    public function deleteDomain(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('Registrar does not support domain removal.');
    }

    public function registerDomain(Registrar_Domain $domain)
    { 
        $ns_list = array();
        foreach ($domain->getNameservers() as $ns) {
            $ns_list[] = array(
                'host' => $ns->getHost(),
            );
        }
        
        $c = $domain->getContactRegistrar();
        
        $params = array(
            'ns' => $ns_list,
            'contact_data' => array(
                'registrant' => array(
                    'email' => $c->getEmail(),
                    'name' => $c->getName(),
                    'street' => array(
                        $c->getAddress1(),
                        $c->getAddress2(),
                        $c->getAddress3(),
                    ),
                    'city' => $c->getCity(),
                    'sp' => $c->getState(),
                    'pc' => $c->getZip(),
                    'cc' => $c->getCountry(),
                    'voice' => '+' . $c->getTelCc() . '.' . $c->getTel(),
                ),
                'tech' => array(
                    'email' => $c->getEmail(),
                    'name' => $c->getName(),
                    'street' => array(
                        $c->getAddress1(),
                        $c->getAddress2(),
                        $c->getAddress3(),
                    ),
                    'city' => $c->getCountry(),
                    'sp' => $c->getState(),
                    'pc' => $c->getZip(),
                    'cc' => $c->getCountry(),
                    'voice' => '+' . $c->getTelCc() . '.' . $c->getTel(),
                    'fax' => ($c->getFax() ? '+' . $c->getFaxCc() . '.' . $c->getFax() : ''),
                ),
                'admin' => array(
                    'email' => $c->getEmail(),
                    'name' => $c->getName(),
                    'street' => array(
                        $c->getAddress1(),
                        $c->getAddress2(),
                        $c->getAddress3(),
                    ),
                    'city' => $c->getCountry(),
                    'sp' => $c->getState(),
                    'pc' => $c->getZip(),
                    'cc' => $c->getCountry(),
                    'voice' => '+' . $c->getTelCc() . '.' . $c->getTel(),
                ),
                'billing' => array(
                    'email' => $c->getEmail(),
                    'name' => $c->getName(),
                    'street' => array(
                        $c->getAddress1(),
                        $c->getAddress2(),
                        $c->getAddress3(),
                    ),
                    'city' => $c->getCity(),
                    'sp' => $c->getState(),
                    'pc' => $c->getZip(),
                    'cc' => $c->getCountry(),
                    'voice' => '+' . $c->getTelCc() . '.' . $c->getTel(),
                ),
            ),
        );
        $this->_request('domains/' . $domain->getName() . '/create', $params);
        
        return true;
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $params = array(
            'curExpDate' => $domain->getExpirationTime(),
        );
        $this->_request('domains/' . $domain->getName() . '/renew', $params);
        
        return true;
    }

    public function isDomainCanBeTransfered(Registrar_Domain $domain)
    {
        throw new Exception('Checking if domain can be transfered is disabled for this registrar');
    }

    public function lock(Registrar_Domain $domain)
    {
        throw new Exception('Registrar does not support domain locking');
    }

    public function unlock(Registrar_Domain $domain)
    {
        throw new Exception('Registrar does not support domain unlocking');
    }

    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        throw new Exception('Registrar does not support privacy protection enable');
    }

    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        throw new Exception('Registrar does not support privacy protection disable');
    }

    public function getEpp(Registrar_Domain $domain)
    {
        throw new Exception('Registrar does not support EPP code retrieval');
    }

	/**
   	 * Runs an api command and returns parsed data.
 	 * @param string $cmd
 	 * @param array $params
 	 * @return array
 	 */
	private function _request($cmd, $params = array())
    {
        $params['login_handle'] = $this->config['user'];
        $params['login_pass'] = $this->config['password'];

        $curl_opts = array(
            CURLOPT_URL => $this->_getApiUrl() . $cmd,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json',
            ),
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode($params),
        );
        
		$ch = curl_init();
            curl_setopt_array($ch, $curl_opts);

		$result = curl_exec($ch);

        if ($result === false) {
            $e = new Registrar_Exception(sprintf('CurlException: "%s"', curl_error($ch)));
            $this->getLog()->err($e);
            curl_close($ch);
            throw $e;
        }
        
        $result = json_decode($result);
        
        $this->getLog()->debug($this->_getApiUrl() . $cmd);
        $this->getLog()->debug(print_r($params, true));
        $this->getLog()->debug(print_r($result, true));
        
		curl_close($ch);
        
        if (strpos($result->code, '100') === false) {
            throw new Registrar_Exception($result->msg . "\n" . implode("\n", $result->error));
        }

        return $result->response;
	}
    
    public function isTestEnv()
    {
        return $this->_testMode;
    }

    /**
     * Api URL.
     * @return string
     */
    private function _getApiUrl()
    {
        if ($this->isTestEnv()) {
            return 'https://httpapi.realtimeregister-ote.com/v1/';
        }
        return 'https://httpapi.yoursrs.com/v1/';
    }
}