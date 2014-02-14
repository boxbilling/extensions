<?php
class Registrar_Adapter_Namesilo extends Registrar_AdapterAbstract
{
    public $config = array(
        'apikey' => null
    );

    public function __construct($options)
    {
        if (!extension_loaded('curl')) {
            throw new Registrar_Exception('CURL extension is not enabled');
        }

        if(isset($options['apikey']) && !empty($options['apikey'])) {
            $this->config['apikey'] = $options['apikey'];
            unset($options['apikey']);
        } else {
            throw new Registrar_Exception('Domain registrar "Namesilo" is not configured properly. Please update configuration parameter "Namesilo Apikey" at "Configuration -> Domain registration".');
        }
    }

    public static function getConfig()
    {
        return array(
            'label' => 'Manages domains on Namesilo via API',
            'form'  => array(
                'apikey' => array('password', array(
                            'label' => 'Namesilo API key',
                            'description'=>'Namesilo API key',
                            'renderPassword' => true,
                    ),
                 ),
            ),
        );
    }

    public function getTlds()
    {
        return array(
            '.com', '.net', '.org', '.biz', '.info', '.mobi',
        );
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $params = array(
            'domains' => $domain->getName(),
        );
        
        $result = $this->_request('checkRegisterAvailability', $params);
        
        return (isset($result->reply->available)
                && ($result->reply->available->domain == $domain->getName()));
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
        );
        
        $i = 1;
        foreach ($domain->getNameservers() as $ns)
            $params['ns' . $i++] = $ns->getHost();
        
        $this->_request('changeNameServers', $params);
        
        return true;
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();
        
        $params = array(
            'domain' => $domain->getName(),
        );
        
        $result = $this->_request('getDomainInfo', $params);
        
        $params = array(
            'contact_id' => (string) $result->reply->contact_ids->registrant,
            
            'fn' => $c->getFirstName(),
            'ln' => $c->getLastName(),
            'ad' => $c->getAddress1(),
            'ad2' => $c->getAddress2(),
            'cy' => $c->getCity(),
            'st' => $c->getState(),
            'zp' => $c->getZip(),
            'ct' => $c->getCountry(),
            'em' => $c->getEmail(),
            'ph' => $c->getTel(),
        );
        
        $this->_request('contactUpdate', $params);
        
        return true;
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
            'auth' => $domain->getEpp(),
        );
        
        $this->_request('transferDomain', $params);
        return true;
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
        );
        
        $result = $this->_request('getDomainInfo', $params);
        $result = $result->reply;
        
        $params = array(
            'contact_id' => (string) $result->contact_ids->registrant,
        );
        
        $contact = $this->_request('contactList', $params);
        $contact = $contact->reply->contact;
        
        $c = new Registrar_Domain_Contact();
        $c->setFirstName((string) $contact->first_name)
          ->setLastName((string) $contact->last_name)
          ->setEmail((string) $contact->email)
          ->setCompany((string) $contact->company)
          ->setTel((string) $contact->phone)
          ->setAddress1((string) $contact->address)
          ->setAddress2((string) $contact->address2)
          ->setCity((string) $contact->city)
          ->setCountry((string) $contact->country)
          ->setZip((string) $contact->zip);

        // Add nameservers
        $ns_list = array();
        foreach ($result->nameservers->nameserver as $ns)
        {
            $n = new Registrar_Domain_Nameserver();
            $n->setHost((string) $ns);
            $ns_list[] = $n;
        }

        $privacy = false;
        if ((string) $result->private == 'Yes')
            $privacy = true;
        
        
        $domain->setNameservers($ns_list);
        $domain->setExpirationTime(strtotime($result->expires));
        $domain->setRegistrationTime(strtotime($result->created));
        $domain->setPrivacyEnabled($privacy);
        //$domain->setEpp(); 
        $domain->setContactRegistrar($c);
        
        return $domain;
        
    }

    public function deleteDomain(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('Registrar does not support domain removal.');
    }

    public function registerDomain(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();
        
        $params = array(
            'domain' => $domain->getName(),
            'years' => 1,
            
            'fn' => $c->getFirstName(),
            'ln' => $c->getLastName(),
            'ad' => $c->getAddress1(),
            'ad2' => $c->getAddress2(),
            'cy' => $c->getCity(),
            'st' => $c->getState(),
            'zp' => $c->getZip(),
            'ct' => $c->getCountry(),
            'em' => $c->getEmail(),
            'ph' => $c->getTel(),
        );
        
        $i = 0;
        foreach ($domain->getNameservers() as $ns)
            $params['ns' . $i] = $ns->getHost();
        
        $result = $this->_request('registerDomain', $params);
        
        return true;
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
            'years' => 1,
        );
        
        $this->_request('renewDomain', $params);
        return true;
    }

    public function togglePrivacyProtection(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
        );
        
        $result = $this->_request('getDomainInfo', $params);
        
        $cmd = 'removePrivacy';
        if ((string) $result->reply->private == 'No')
            $cmd = 'addPrivacy';
        
        $this->_request($cmd, $params);
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
	private function _request($cmd, $params)
    {
        $params['version'] = 1;
        $params['type'] = 'xml';
        $params['key'] = $this->config['apikey'];
        
        $query = http_build_query($params);
        
        $curl_opts = array(
            CURLOPT_URL => $this->_getApiUrl() . $cmd . '?' . $query,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => 1,
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
		curl_close($ch);
        
        $this->getLog()->debug($this->_getApiUrl() . $cmd . '?' . $query);
        $this->getLog()->debug(print_r($result, true));

        try {
            $xml = new SimpleXMLElement($result);
        } catch (Exception $e) {
            throw new Registrar_Exception($e->getMessage());
        }
        
        if ($xml->reply->code != 300)
            throw new Registrar_Exception($xml->reply->detail);
        
        return $xml;
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
        if ($this->isTestEnv())
            return 'http://sandbox.namesilo.com/api/';
        return 'https://namesilo.com/api/';
    }
}
