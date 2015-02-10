<?php
class Registrar_Adapter_Netregistry extends Registrar_AdapterAbstract
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
            throw new Registrar_Exception('Domain registrar "Netregistry" is not configured properly. Please update configuration parameter "Netregistry Login" at "Configuration -> Domain registration".');
        }

        if(isset($options['password']) && !empty($options['password'])) {
            $this->config['password'] = $options['password'];
            unset($options['password']);
        } else {
            throw new Registrar_Exception('Domain registrar "Netregistry" is not configured properly. Please update configuration parameter "Netregistry Password" at "Configuration -> Domain registration".');
        }
    }

    public static function getConfig()
    {
        return array(
            'label'     =>  'Manages domains on Netregistry via API',
            'form'  => array(
                'user' => array('text', array(
                            'label' => 'Netregistry Login',
                            'description'=>'Netregistry Login'
                        ),
                     ),
                'password' => array('password', array(
                            'label' => 'Netregistry password',
                            'description'=>'Netregistry password',
                            'renderPassword'    =>  true,
                        ),
                     ),
            ),
        );
    }

    /**
     * Return array of TLDs current Registar is capable to register
     * If function returns empty array, this registrar can register any TLD
     * @return array
     */
    public function getTlds()
    {
        return array(
            '.com.au', '.com', '.net.au', '.net', 
            '.org.au', '.org', '.co.nz', '.co.uk', 
            '.info', '.biz', '.au.com', '.id.au', 
            '.asn.au', '.asia', '.net.nz', '.org.nz', 
            '.geek.nz', '.org.uk', '.eu', '.tv', '.mobi','.us', 
        );
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
        );
        
        $result = $this->_request('domainLookup', $params);

        return true;
    }

    public function isDomainCanBeTransfered(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('Domain transfer checking is not implemented');
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function modifyNs(Registrar_Domain $domain)
    {
        $nsList = array();
        foreach ($domain->getNameservers() as $ns)
            $nsList[] = $ns->getHost();
        
        $params = array(
            'domain' => $domain->getName(),
            'nameServers' => $nsList,
        );

        $result = $this->_request('updateDomainNS', $params);

        return true;
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function modifyContact(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
        );
        
        $result = $this->_request('domainInfo', $params);
        $contact_id = $result->entries[4]->value;
        
        $c = $domain->getContactRegistrar();
        $params = array(
            'domain' => $domain->getName(),
            'nicHandle' => $contact_id,
            'contactDetails' => array(
                'firstName' => $c->getFirstName(),
                'lastName' =>	$c->getLastName(),
                'organisation' => $c->getCompany(),
                'state' => $c->getState(),
                'address1' =>	$c->getAddress1(),
                'address2' =>	$c->getAddress2(),
                'suburb' =>	$c->getState(),
                'postcode' =>	$c->getZip(),
                'country'	=> $c->getCountry(),
                'phone' => $c->getTelCc() . $c->getTel(),
                'email' => $c->getEmail(),
            ),
        );
        
        $result = $this->_request('contactUpdate', $params);
        
        return true;
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function transferDomain(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
        );
        
        $result = $this->_request('domainInfo', $params);
        $contact_id = $result->entries[4]->value;
        
        $c = $domain->getContactRegistrar();
        $params = array(
            'domain' => $domain->getName(),
            'nicHandle' => $contact_id,
            'period' => '1',
            'authcode' => $this->_getEPP($domain),
            'contactDetails' => array(
                'firstName' => $c->getFirstName(),
                'lastName' => $c->getLastName(),
                'organisation' => $c->getCompany(),
                'state' => $c->getState(),
                'address1' => $c->getAddress1(),
                'address2' => $c->getAddress2(),
                'suburb' =>	$c->getState(),
                'postcode' => $c->getZip(),
                'country' => $c->getCountry(),
                'phone' => $c->getTelCc() . $c->getTel(),
                'email' => $c->getEmail(),
            ),
        );
        
        $result = $this->_request('transferDomain', $params);
        
        return true;
    }

    /**
     * Should return details of registered domain
     * If domain is not registered should throw Registrar_Exception
     * @return Registrar_Domain
     * @throws Registrar_Exception
     */
    public function getDomainDetails(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
        );
        
        $result = $this->_request('domainInfo', $params);
        $contact_id = $result->entries[4]->value;

        $params = array(
            'domain' => $domain->getName(),
            'nicHandle' => $contact_id,
        );
        
        $result1 = $this->_request('contactInfo', $params);
        
        $c = new Registrar_Domain_Contact();
        $c->setFirstName($result1->entries[0]->value)
          ->setLastName($result1->entries[8]->value)
          ->setEmail($result1->entries[6]->value)
          ->setCompany($result1->entries[3]->value)
          ->setTel($result1->entries[1]->value)
          ->setAddress1($result1->entries[10]->value)
          ->setAddress2($result1->entries[9]->value)
          ->setCity($result1->entries[2]->value)
          ->setCountry($result1->entries[12]->value)
          ->setZip($result1->entries[7]->value);

        // Add nameservers
        $nsList = array();
        foreach ($result->entries as $entry)
            if (strpos($entry->key, 'ns.name') !== false)
            {
                $n = new Registrar_Domain_Nameserver();
                $n->setHost($entry->value);
                $nsList[] = $n;
            }

        $domain->setNameservers($nsList);
        $domain->setExpirationTime(strtotime($result->entries[7]->value));
        $domain->setRegistrationTime(strtotime($result->entries[3]->value));
        //$domain->setPrivacyEnabled($privacy);
        $domain->setEpp($this->_getEPP($domain));
        $domain->setContactRegistrar($c);
 
        return $domain;
    }

    public function deleteDomain(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('Registrar does not support domain removal.');
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function registerDomain(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();
        
        $nsList = array();
        foreach ($domain->getNameservers() as $ns)
        {
            $nsList[] = $ns->getHost();
        }
        
        $params = array(
            'domain' => $domain->getName(),
            'contactDetails' => array(
                'firstName' => $c->getFirstName(),
                'lastName' =>	$c->getLastName(),
                'organisation' => $c->getCompany(),
                'state' => $c->getState(),
                'address1' =>	$c->getAddress1(),
                'address2' =>	$c->getAddress2(),
                'suburb' =>	$c->getState(),
                'postcode' =>	$c->getZip(),
                'country'	=> $c->getCountry(),
                'phone' => $c->getTelCc() . $c->getTel(),
                'email' => $c->getEmail(),
            ),
            'period' => $domain->getRegistrationPeriod(),
            'nameServers' => $nsList,
        );	
        
        if ($domain->getTld() == 'au')
        {
            $params['eligibility'] = array(
                array(
                    'key' => 'au.registrant.name',	
                    'value' => $c->getName(),
                ), 
                array(
                    'key' => 'au.registrantid.type',	
                    'value' => 'ABN',
                ),
                array(
                    'key'	=> 'au.registrant.number', 
                    'value' => 123456789,
                ),
                array(
                    'key' => 'au.org.type', 
                    'value' => 'Company',
                ),
            );
        } 
        
        if ($domain->getTld() == 'asia')
        {
            $params['eligibility'] = array(
                array(
                    'key' => 'asia.country',	
                    'value' => $c->getCountry(),
                ), 
                array(
                    'key'	=> 'asia.legal.entity.type', 
                    'value' => 'Natural Person',
                ),
                array(
                    'key' => 'asia.id.form', 
                    'value' => 'Passport',
                ),
                array(
                    'key' => 'asia.id.number', 
                    'value' => $c->getDocumentNr(),
                ),
            );
        }
        
        if ($domain->getTld() == 'ca')
        {
            $params['eligibility'] = array(
                array(
                    'key' => 'ca.legal.entity.type',	
                    'value' => 'CCO',
                ), 
                array(
                    'key'	=> 'ca.registrant.language', 
                    'value' => 'English',
                ),
            );
        }
        
        if ($domain->getTld() == 'es')
        {
            $params['eligibility'] = array(
                array(
                    'key' => 'es.id.type',	
                    'value' => 'Other',
                ), 
                array(
                    'key'	=> 'es.id.number', 
                    'value' => '123',
                ),
            );
        }
        $result = $this->_request('registerDomain', $params);
        
        return true;
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function renewDomain(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
            'period' => '1',
        );
        
        $result = $this->_request('renewDomain', $params);
        
        return true;
    }

    /**
     * @throws Registrar_Exception
     */
    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
    	throw new Registrar_Exception("Netregistry does not support Privacy protection");
    }

    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
    	throw new Registrar_Exception("Netregistry does not support Privacy protection");
    }
    
    public function getEpp(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('Epp code retrieval is not implemented');
    }

    public function lock(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('Domain locking is not implemented');
    }

    public function unlock(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('Domain unlocking is not implemented');
    }

	/**
   	 * Runs an api command and returns data.
 	 * @param string $command
 	 * @param array $params
 	 * @return array
 	 */
    private function _request($cmd, $params)
    {
        $client	= new SoapClient(
                $this->_getApiUrl() . '?wsdl',	array(
                    'login' => $this->config['user'],	
                    'password' => $this->config['password'],
                )
        );	 
        $client->__setLocation($this->_getApiUrl());
        
        $result = $client->$cmd($params);
        
        $this->getLog()->debug(print_r($result, true));
        $this->getLog()->debug(print_r($params, true));
        
        if ($result->return->success == 'FALSE')
        {
            if (is_array($result->return->errors))
            {
                $error_msg = '';
                foreach ($result->return->errors as $error)
                    $error_msg .= $error->errorMsg . ' ';
            }
            else
                $error_msg = $result->return->errors->errorMsg;
            throw new Registrar_Exception($error_msg);
        }
        
        return $result->return->fields;
    }

    /**
     * Api URL.
     * @return string
     */
    private function _getApiUrl()
    {
        return 'https://theconsole.netregistry.com.au/external/services/ResellerAPIService/';
    }
    
    /**
     * Gets transfer (authcode) of the domain.
     * @param Registrar_Domain $domain
     * @return string
     */
    private function _getEPP(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
        );
        
        return $this->_request('domainAuthCode', $params)->entries[0]->value;
    }
}