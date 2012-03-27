<?php
class Registrar_Adapter_Namecheap extends Registrar_AdapterAbstract
{
    public $config = array(
        'user'    =>  null,
        'apiKey'  =>  null
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
            throw new Registrar_Exception('Domain registrar "Namecheap" is not configured properly. Please update configuration parameter "Namecheap API Username" at "Configuration -> Domain registration".');
        }

        if(isset($options['apiKey']) && !empty($options['apiKey'])) {
            $this->config['apiKey'] = $options['apiKey'];
            unset($options['apiKey']);
        } else {
            throw new Registrar_Exception('Domain registrar "Namecheap" is not configured properly. Please update configuration parameter "Namecheap API key" at "Configuration -> Domain registration".');
        }
    }
    
    public static function getConfig()
    {
        return array(
            'label'     =>  'Manages domains on Namecheap via API',
            'form'  => array(
                'user' => array('text', array(
                            'label' => 'Namecheap API Username', 
                            'description'=>'Namecheap API Username'
                        ),
                     ),
                'apiKey' => array('password', array(
                            'label' => 'Namecheap API key', 
                            'description'=>'Namecheap API key',
                            'renderPassword'    =>  true, 
                        ),
                     ),
            ),
        );
    }
    
    /**
     * @return array
     */
    public function getTlds() {
    	$params = array (
    		'command'	=>	'namecheap.domains.gettldlist'
    	);
    	
    	$response = $this->_makeRequest($params, new Registrar_Domain());
    	
    	$tlds = array();
    	foreach($response->CommandResponse->Tlds->Tld as $tld) {
    		$tlds[] = '.' . (string)$tld->attributes()->Name;
    	}
    	
    	return $tlds;
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function isDomainAvailable(Registrar_Domain $domain) {
    	$params = array(
    		'command'	=>	'namecheap.domains.check',
    		'DomainList'=>	$domain->getName()
    	);
    	
    	$response = $this->_makeRequest($params, $domain);
    	
    	foreach ($response->CommandResponse->DomainCheckResult as $result) {
    		if ($result->attributes()->Domain == $domain->getName()
    			&& $result->attributes()->Available == 'true') {
    				return true;
    			}
    	}
    	
    	return false;
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function modifyNs(Registrar_Domain $domain) {
    	$oldNs = $this->_getNameservers($domain);
    	
    	$del_domain = $domain;
    	$del_domain->setNameservers($oldNs);
    	
    	$this->_deleteNameservers($del_domain);
    	
    	if (!$this->_setNameservers($domain)) {
    		return false;
    	}
    	
    	return true;
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function modifyContact(Registrar_Domain $domain) {
    	$params = array(
    		'command'		=>	'namecheap.domains.setContacts',
    		'DomainName'	=>	$domain->getName(),
    		'UserName'		=>	$this->_getUsername($domain)
    	);
    	
    	$c = $domain->getContactRegistrar();
    	$types = array('Registrant', 'Tech', 'Admin', 'AuxBilling');
    	foreach($types as $type) {
    		$params[$type . 'FirstName']		=	$c->getFirstName();
    		$params[$type . 'LastName']			=	$c->getLastName();
    		$params[$type . 'Address1']			=	$c->getAddress1();
    		$params[$type . 'City']				=	$c->getCity();
    		$params[$type . 'StateProvince']	=	$c->getState();
    		$params[$type . 'PostalCode']		=	$c->getZip();
    		$params[$type . 'Country']			=	$c->getCountry();
    		$params[$type . 'Phone']			=	$c->getTel() ? '+'.$c->getTelCc().'.'.$c->getTel() : '';
    		$params[$type . 'EmailAddress']		=	$c->getEmail();
    		

    		$optional_params[$type . 'OrganizationName']	=	$c->getCompany();
    		$optional_params[$type . 'JobTitle']			=	$c->getJobTitle();
    		$optional_params[$type . 'Address2']			=	$c->getAddress2();
    		$optional_params[$type . 'Fax']					=	$c->getFax() ? '+'.$c->getFaxCc().'.'.$c->getFax() : '';
    	}
        
        $params = array_merge($params, $optional_params);
        
        $result = $this->_makeRequest($params, $domain);
		$attr = $result->CommandResponse->DomainSetContactResult->attributes();
		if ($attr->Domain == $domain->getName() && $attr->IsSuccess == 'true') {
			return true;
		} else {
			return false;
		}
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function transferDomain(Registrar_Domain $domain) {
    	throw new Registrar_Exception('Can\'t transfer domain using Namecheap API');	
    }

    /**
     * Should return details of registered domain
     * If domain is not registered should throw Registrar_Exception
     * @return Registrar_Domain
     * @throws Registrar_Exception
     */
    public function getDomainDetails(Registrar_Domain $domain) {
    	$domain->setContactRegistrar($this->_getContactDetails($domain));
    	$domain->setNameservers($this->_getNameservers($domain));
    	$domain = $this->_getDomainDates($domain);
    	
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
    public function registerDomain(Registrar_Domain $domain) {
    	if (!$this->_checkUserExists($domain)) {
    		$this->_createUser($domain);
    	} 
    	
    	$params = array(
    		'command'		=>	'namecheap.domains.create',
    		'DomainName'	=>	$domain->getName(),
    		'Years'			=>	1,
    		'UserName'		=>	$this->_getUsername($domain)
    	);
    	
    	$optional_params = array(
    		'PromotionCode'					=>	'',
    	);
    	
    	$c = $domain->getContactRegistrar();
    	$types = array('Registrant', 'Tech', 'Admin', 'AuxBilling');
    	foreach($types as $type) {
    		$params[$type . 'FirstName']		=	$c->getFirstName();
    		$params[$type . 'LastName']			=	$c->getLastName();
    		$params[$type . 'Address1']			=	$c->getAddress1();
    		$params[$type . 'City']				=	$c->getCity();
    		$params[$type . 'StateProvince']	=	$c->getState();
    		$params[$type . 'PostalCode']		=	$c->getZip();
    		$params[$type . 'Country']			=	$c->getCountry();
    		$params[$type . 'Phone']			=	$c->getTel() ? '+'.$c->getTelCc().'.'.$c->getTel() : '';
    		$params[$type . 'EmailAddress']		=	$c->getEmail();
    		

    		$optional_params[$type . 'OrganizationName']	=	$c->getCompany();
    		$optional_params[$type . 'JobTitle']			=	$c->getJobTitle();
    		$optional_params[$type . 'Address2']			=	$c->getAddress2();
    		$optional_params[$type . 'Fax']					=	$c->getFax() ? '+'.$c->getFaxCc().'.'.$c->getFax() : '';
    	}
    	
    	$params['Nameservers'] = '';
        foreach($domain->getNameservers() as $nse) {
            $params['Nameservers'] .= ($params['Nameservers']) ? ',' . $nse->getHost() : $nse->getHost();
        }
        
        $params = array_merge($params, $optional_params);
        
        $result = $this->_makeRequest($params, $domain);
        
        if ($result->CommandResponse->DomainCreateResult->attributes()->Domain == $domain->getName()
        	&& $result->CommandResponse->DomainCreateResult->attributes()->Registered == 'true') {
        		return true;
        	} else {
        		return false;
        	}
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function renewDomain(Registrar_Domain $domain) {
    	$params = array(
    		'command'		=>	'namecheap.domains.renew',
    		'DomainName'	=>	$domain->getName(),
    		'Years'			=>	1,
    		'UserName'		=>	$this->_getUsername($domain)
    	);	
    	
    	$response = $this->_makeRequest($params, $domain);
    	if ($response->CommandResponse->DomainRenewResult->attributes()->DomainName == $domain->getName()
    		&& $response->CommandResponse->DomainRenewResult->attributes()->Renew == 'true') {
    			return true;
    	} else {
    			return false;
    	}
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
     * Makes API call
     * @param array $params
     * @param string $type
     * @return bool
     * @throws Registrar_Exception
     */
    private function _makeRequest($params = array(), Registrar_Domain $domain, $type = 'xml') {
        $params = array_merge(array(
            'ApiUser'   =>  $this->config['user'],
            'ApiKey'    =>  $this->config['apiKey'],
        	'UserName'  =>	isset($params['UserName']) ? $params['UserName'] : $this->config['user'],
        ), $params);

		$c = $domain->getContactRegistrar();
        $clientIp = isset($params['UserName']) ? $this->_getIp($c, false) : $this->_getIp($c);
        
        $opts = array(
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_TIMEOUT         => 60,
            CURLOPT_USERAGENT       => 'http://fordnox.com',
            CURLOPT_URL             => $this->_getApiUrl().'?ClientIp='.$clientIp,
            //workaround to prevent errors related to SSL
            CURLOPT_SSL_VERIFYHOST	=> 0,
            CURLOPT_SSL_VERIFYPEER	=> 0,
             
        );

        foreach ($params as $key => $param)
        {
            if (is_array($param)) $param = implode(',',$param);
            $opts[CURLOPT_URL] .= '&'.$key.'='.urlencode((string)$param);
        }
        $this->getLog()->debug($opts[CURLOPT_URL]);

        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $result = curl_exec($ch);
        if ($result === false) {
            $e = new Registrar_Exception(sprintf('CurlException: "%s"', curl_error($ch)));
            $this->getLog()->err($e);
            curl_close($ch);
            throw $e;
        }
        curl_close($ch);

        return $this->_parseResponse($result, $type);
    }
    
    /**
     * 
     * get server or client ip
     * @param Registrar_Domain_Contact $c
     * @param boolean $server
     * @throws Registrar_Exception
     * @return string
     */
    private function _getIp(Registrar_Domain_Contact $c, $server = true) {
    	if ($this->_isTestEnv()) return '87.247.113.219';
    	
    	if($server) return $_SERVER['REMOTE_ADDR'];
    	
    	$table = Doctrine_Core::getTable('Model_ActivityClientHistory');
    	$row = $table->findOneByClientId($c->id);
    	
    	if ($row instanceof Model_ActivityClientHistory) return $row->ip;
    	else throw new Registrar_Exception('NamecheapApiError: can\'t find client\'s ip');
    }
    
    
    private function _isTestEnv()
    {
        return $this->_testMode;
    }

    /**
     * Api URL
     * @return string
     */
    private function _getApiUrl()
    {
        if($this->_isTestEnv()) {
            return 'https://api.sandbox.namecheap.com/xml.response';
        }
        return 'https://api.namecheap.com/xml.response';
    }
    
    /**
     * Parses response
     * @param string $result
     * @param string $type
     * @return object
     */
    private function _parseResponse($result, $type = 'xml')
    {
        try
        {
            $xml = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);
        } catch (Exception $e) {
            throw new Registrar_Exception('simpleXmlException: '.$e->getMessage());
        }

        if (isset($xml->Errors) && count($xml->Errors->Error) > 0) {
            throw new Registrar_Exception(sprintf('NamecheapApiError: "%s"', $xml->Errors->Error[0]), 100);
        }
        
        return $xml;
    }
    
    private function _createUser(Registrar_Domain $d) {
    	$c = $d->getContactRegistrar();
    	$params = array(
    		'command'			=>	'namecheap.users.create',
    		'NewUserName'		=>	$this->_getUsername($d),
    		'NewUserPassword'	=>	$this->_getPassword($d),
    		'EmailAddress'		=>	$c->getEmail(),
    		'Firstname'			=>	$c->getFirstName(),
    		'LastName'			=>	$c->getLastName(),
    		'AcceptTerms'		=>	1,
    		'AcceptNews'		=>	0,
    		'JobTitle'			=>	$c->getJobTitle(),
    		'Organization'		=>	$c->getCompany(),
    		'Address1'			=>	$c->getAddress1(),
    		'Address2'			=>	$c->getAddress2(),
    		'City'				=>	$c->getCity(),
    		'StateProvince'		=>	$c->getState(),
    		'Zip'				=>	$c->getZip(),
    		'Country'			=>	$c->getCountry(),
    		'Phone'				=> 	$c->getTel() ? '+'.$c->getTelCc().'.'.$c->getTel() : '',
    		'Fax'				=>	$c->getFax() ? '+'.$c->getFaxCc().'.'.$c->getFax() : ''
    	);

    	$response = $this->_makeRequest($params, $d);
    	
    	if ($response->CommandResponse->UserCreateResult->attributes()->Success == 'true') {
    		return true;
    	} else {
    		return false;
    	}
    }
    
    /**
     * 
     * Checks if client has account in namecheap
     * @param Registrar_Domain $d
     */
    private function _checkUserExists(Registrar_Domain $d) {
    	$c = $d->getContactRegistrar();
    	$params = array(
    		'command'		=>	'namecheap.users.login',
    		'Password'		=>	$this->_getPassword($d),
    		'UserName'		=>	$this->_getUsername($d)
    	);
    	
    	try {
    		$response = $this->_makeRequest($params, $d);
    	} catch (Registrar_Exception $e) {
    		return false;
    	}
    	
    	if ($response->CommandResponse->UserLoginResult->attributes()->LoginSuccess == 'true') {
    		return true;
    	} else {
    		return false;
    	}
    }
    
    /**
     * 
     * Generates password for namecheap user
     * @param unknown_type $d
     */
    private function _getPassword(Registrar_Domain $d) {
    	$c = $d->getContactRegistrar();
    	
    	return substr(MD5($this->config['apiKey'] . $c->getEmail()), 0, 20);
    }
    
    /**
     * 
     * Generates username for namecheap
     * @param unknown_type $d
     */
    private function _getUsername(Registrar_Domain $d) {
    	$email = $d->getContactRegistrar()
    			   ->getEmail();

		$email = strtolower($email);
    	$email = preg_replace('/[^a-z0-9]/', '', $email);
    	
    	$email_length = strlen($email);
    	if($email_length < 6) {
    		for ($i=$email_length; $i<6; $i++) {
    			$email .= '0';
    		}
    	} elseif ($email_length > 20) {
    		$email = substr($email, 0, 20);
    	}

    	return $email;
    }
    
    /**
     * 
     * Gets registrar lock status for domain
     * @param Registrar_Domain $domain
     * @return boolean
     */
	private function _getRegistrarLock(Registrar_Domain $domain) {
		$params = array (
			'command'		=>	'namecheap.domains.getRegistrarLock',
			'UserName'		=>	$this->_getUsername($domain),
			'DomainName'	=>	$domain->getName()
		);
		
		$response = $this->_makeRequest($params, $domain);
		if ($response->CommandResponse->DomainGetRegistrarLockResult->attributes()->Domain == $domain->getName()
			&& $response->CommandResponse->DomainGetRegistrarLockResult->attributes()->RegistrarLockStatus == 'true') {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * 
	 * Switches registrar lock
	 * ($lock = true to lock; $lock = false to unlock);
	 * 
	 * @param Registrar_Domain $domain
	 * @param boolean $lock
	 */
	private function _registrarLock(Registrar_Domain $domain, $lock = true) {
		$currentLock = $this->_getRegistrarLock($domain);
		if ($currentLock && $lock) {
			return true;
		} elseif ($currentLock && !$lock) {
			$lockAction = 'UNLOCK';
		} elseif (!$currentLock && $lock) {
			$lockAction = 'LOCK';
		} else {
			return true;
		}
		
		$params = array(
			'command'		=>	'namecheap.domains.setRegistrarLock',
			'DomainName'	=>	$domain->getName(),
			'LockAction'	=>	$lockAction,
			'UserName'		=>	$this->_getUsername($domain)
		);
		
		$response = $this->_makeRequest($params, $domain);
		if ($response->CommandResponse->DomainSetRegistrarLockResult->attributes()->Domain == $domain->getName()
			&& $response->CommandResponse->DomainSetRegistrarLockResult->attributes()->IsSuccess == 'true') {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * 
	 * Get domain contacts info
	 * @param Registrar_Domain $domain
	 * @return Registrar_Domain_Contact
	 */
	private function _getContactDetails(Registrar_Domain $domain) {
		$params = array(
			'command'		=>	'namecheap.domains.getContacts',
			'DomainName'	=>	$domain->getName(),
			'UserName'		=>	$this->_getUsername($domain)
		);
		
		$response = $this->_makeRequest($params, $domain);
		$result = $response->CommandResponse->DomainContactsResult->Registrant;

		$tel = $this->_separateTelephone($result->Phone);
		$fax = $this->_separateTelephone($result->Fax);
		
		$c = new Registrar_Domain_Contact();
		$c->setCompany((string)$result->OrganizationName)
		  ->setFirstName((string)$result->FirstName)
		  ->setLastName((string)$result->LastName)
		  ->setAddress1((string)$result->Address1)
		  ->setAddress2((string)$result->Address2)
		  ->setCity((string)$result->City)
		  ->setState((string)$result->StateProvince)
		  ->setZip((string)$result->PostalCode)
		  ->setCountry((string)$result->Country)
		  ->setTelCc($tel['code'])
		  ->setTel($tel['tel'])
		  ->setFaxCc($fax['code'])
		  ->setFax($fax['tel'])
		  ->setEmail((string)$result->EmailAddress);
		  
		  return $c;
	}
	
    /**
     * 
     * Separates telephone from international calling code
     * @param string $tel
     * @return array|string
     */
    private function _separateTelephone($tel) {    	
    	$return = array(
    		'code'	=>	'',
    		'tel'	=>	''
    	);
    	
        $tel = str_replace(array('+', '(', ')', ' '), '', $tel);
        
    	if (strlen($tel) < 2) {
			return $return;
		}
		
		$tel = explode('.', $tel);
		
		if (isset($tel[0]) && !empty($tel[0]) && isset($tel[1]) && !empty($tel[1])) {
			$return['code'] = $tel[0]; 
			$return['tel'] = $tel[1];
		}

		return $return;
    }
    
    /**
     * 
     * Get nameservers list for domain
     * @param Registrar_Domain $domain
     * @return array of Registrar_Domain_Nameserver
     */
    private function _getNameservers(Registrar_Domain $domain) {
    	$params = array(
    		'command'		=>	'namecheap.domains.dns.getList',
    		'SLD'			=>	$domain->getSld(),
    		'TLD'			=>	$domain->getTld(),
    		'UserName'		=>	$this->_getUsername($domain)
    	);
    	
    	$response = $this->_makeRequest($params, $domain);
    	$result = $response->CommandResponse->DomainDNSGetListResult->Nameserver;
    	
    	$ns = array();
    	foreach ($result as $nameserver) {
    		$n = new Registrar_Domain_Nameserver();
    		$n->setHost((string)$nameserver);
    		
    		$ns[] = $n;
    	}
    	
    	return $ns;
    }
    
    /**
     * 
     * Gets domain registration and expiration dates
     * @param Registrar_Domain $domain
     * @return Registrar_Domain
     */
    private function _getDomainDates(Registrar_Domain $domain) {
    	$params = array (
    		'command'		=>	'namecheap.domains.getList',
    		'ListType'		=>	'ALL',
    		'SearchTerm'	=>	$domain->getName(),
    		'UserName'		=>	$this->_getUsername($domain)
    	);
    	
    	$response = $this->_makeRequest($params, $domain);
    	
    	$result = $response->CommandResponse->DomainGetListResult->Domain->attributes();
    	$domain->setExpirationTime(strtotime((string)$result->Expires));
    	$domain->setRegistrationTime(strtotime((string)$result->Created));
    	$domain->setEpp('NAMECHEAP');
    	
    	return $domain;
    }
    
    /**
     * 
     * Deletes nameservers
     * 
     * @param Registrar_Domain $domain
     * @throws Registrar_Exception
     * @return boolean
     */
    private function _deleteNameservers(Registrar_Domain $domain) {
    	$ns = $domain->getNameservers();
    	
    	foreach($ns as $n) {
    		if (!$n instanceof Registrar_Domain_Nameserver) {
    			throw new Registrar_Exception('Nameservers should be objects of Registrar_Domain_Nameserver');
    		}
    		
    		if (!$n->getHost()) {
    			throw new Registrar_Exception('Host of nameserver can\'t be empty');
    		}
    		
    		$params = array(
    			'command'		=>	'namecheap.domains.ns.delete',
    			'UserName'		=>	$this->_getUsername($domain),
    			'SLD'			=>	$domain->getSld(),
    			'TLD'			=>	$domain->getTld(),
    			'Nameserver'	=>	$n->getHost()
    		);
    		
    		$response = $this->_makeRequest($params, $domain);
    		if ($response->CommandResponse->DomainNSDeleteResult->attributes()->Nameserver != $n->getHost()
    			|| $response->CommandResponse->DomainNsDeleteResult->attributes()->IsSuccess != 'true') {
				throw new Registrar_Exception('Domain or nameserver mismatch');				    				
    		}
    	}
    	
    	return true;
    }
    
    /**
     * 
     * Set nameservers
     * @param Registrar_Domain $domain
     * @throws Registrar_Exception
     * @return boolean
     */
    private function _setNameservers(Registrar_Domain $domain) {
    	$ns = $domain->getNameservers();
    	
    	foreach($ns as $n) {
    		if (!$n instanceof Registrar_Domain_Nameserver) {
    			throw new Registrar_Exception('Nameservers should be objects of Registrar_Domain_Nameserver');
    		}
    		
    		if (!$n->getHost()) {
    			throw new Registrar_Exception('Host of nameserver can\'t be empty');
    		}
    		
    		if (!$n->getIp()) {
    			throw new Registrar_Exception('IP of nameserver can\'t be empty');
    		}
    		
    		$params = array(
    			'command'		=>	'namecheap.domains.ns.create',
    			'UserName'		=>	$this->_getUsername($domain),
    			'SLD'			=>	$domain->getSld(),
    			'TLD'			=>	$domain->getTld(),
    			'Nameserver'	=>	$n->getHost(),
    			'IP'			=>	$n->getIP()
    		);
    		
    		$response = $this->_makeRequest($params, $domain);
    		if ($response->DommandResponse->DomainNSCreateResult->attributes()->IsSuccess == "true") {
    			return true;
    		} else {
    			return false;
    		}
    	}
    }
}