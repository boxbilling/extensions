<?php
class Registrar_Adapter_Enom extends Registrar_AdapterAbstract
{
    public $config = array(
        'user'    =>  null,
        'password'  =>  null
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
            throw new Registrar_Exception('Domain registrar "Enom" is not configured properly. Please update configuration parameter "Enom Username" at "Configuration -> Domain registration".');
        }

        if(isset($options['password']) && !empty($options['password'])) {
            $this->config['password'] = $options['password'];
            unset($options['password']);
        } else {
            throw new Registrar_Exception('Domain registrar "Enom" is not configured properly. Please update configuration parameter "Enom Password" at "Configuration -> Domain registration".');
        }
    }
    
    public static function getConfig()
    {
        return array(
            'label'     =>  'Manages domains on Enom via API',
            'form'  => array(
                'user' => array('text', array(
                            'label' => 'Enom Username', 
                            'description'=>'Enom Username'
                        ),
                     ),
                'password' => array('password', array(
                            'label' => 'Enom Pasword', 
                            'description'=>'Enom Password',
                            'renderPassword'    =>  true, 
                        ),
                     ),
            ),
        );
    }
    
    public function getTlds()
    {
        return array(
            '.asia', '.biz', '.com', '.info', '.mobi', '.name', '.net', '.org',
            '.pro', '.tel',
            '.ac', '.am', '.at', '.be', '.bz', '.ca', '.cc', '.cm', '.cn', '.co',
            '.de', '.eu', '.fm', '.gs', '.in', '.io', '.it', '.jp', '.la', '.me',
            '.ms', '.nl', '.nu', '.nz', '.sh', '.tc', '.tm', '.tv', '.tw', '.us',
            '.uk', '.vg', '.ws',
            '.br.com', '.cn.com', '.de.com', '.eu.com', '.hu.com', '.no.com',
            '.qc.com', '.ru.com', '.sa.com', '.se.com', '.uk.com', '.us.com',
            '.uy.com', '.za.com', '.com.mx', '.uk.net', '.se.net', '.kids.us'
        );
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $params = array(
            'command'   =>  'check',
            'SLD'       =>  $domain->getSld(),
            'TLD'       =>  $domain->getTld()
        );

        $result = $this->_makeRequest($params);
        if (isset($result->RRPCode) && $result->RRPCode == 210) return true;
        else return false;
    }

    public function isDomainCanBeTransfered(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('Domain transfer checking is not implemented');
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        $params = array(
            'command'   =>  'ModifyNS',
            'SLD'       =>  $domain->getSld(),
            'TLD'       =>  $domain->getTld()
        );
        $i = 1;
        foreach($domain->getNameservers() as $nse) {
            $params['NS'.$i] = $nse->getHost();
            $i++;
        }

        $result = $this->_makeRequest($params);
        if (isset($result->ErrCount) && $result->ErrCount == 0) return true;	
        else return false;	
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
        $params = array(
            'command'   =>  'GetWhoisContact',
            'SLD'       =>  $domain->getSld(),
            'TLD'       =>  $domain->getTld()
        );

        $result = $this->_makeRequest($params);
        if (isset($result->RRPCode) && $result->RRPCode != 200)
        {
            throw new Registrar_Exception(sprintf('EnomApiError: "%s"', implode(', ', $result->errors)), 100);
        }

        $rrp = $result->GetWhoisContacts->{'rrp-info'};
        $domain->setRegistrationTime(strtotime((string)$rrp->{'updated-date'}));
        $domain->setExpirationTime(strtotime((string)$rrp->{'registration-expiration-date'}));
        $domain->setEpp('ENOM');

        //nameservers
        $params = array(
        	'command'	=>	'GetDomainInfo',
        	'TLD'		=>	$domain->getTld(),
        	'SLD'		=>	$domain->getSld()
        );
        $result = $this->_makeRequest($params);
        if (isset($result->GetDomainInfo->services->entry) && !empty($result->GetDomainInfo->services->entry)) {
            foreach($services = $result->GetDomainInfo->services->entry as $service) {
            	if (isset($service->service) && (int)$service->service == 1012) {
            		$ns = $service->configuration->dns;
            	} elseif (isset($service->service) && (int)$service->service == 1120 && isset($service->configuration)) {
            		$domain->setPrivacyEnabled(true);
            	}
            }

            $ns_list = array();
            foreach ($ns as $s) {
            	$s = (string)$s;
                $n = new Registrar_Domain_Nameserver();
                $ns_list[] = $n->setHost($s);
            }

            $domain->setNameservers($ns_list);
        }
        
        //contacts
        $params = array(
        	'command'		=>	'getcontacts',
        	'TLD'			=>	$domain->getTld(),
        	'SLD'			=>	$domain->getSld(),
        );
        $result = $this->_makeRequest($params);
        $wc = $result->GetContacts->Registrant;
        $t = $this->_separateTelephone($wc->RegistrantPhone);
        $telcc = (isset($t['code'])) ? $t['code'] : '';
        $tel = (isset($t['tel'])) ? $t['tel'] : str_replace(array(' ', '.', '(', ')', '-'), '', $wc->Phone);
        $c = new Registrar_Domain_Contact();
        $c->setName((string)$wc->RegistrantFirstName.' '.(string)$wc->RegistrantLastName)
          ->setFirstName((string)$wc->RegistrantFirstName)
          ->setLastName((string)$wc->RegistrantLastName)
          ->setEmail((string)$wc->RegistrantEmailAddress)
          ->setCompany((string)$wc->RegistrantOrganizationName)
          ->setTel($tel)
          ->setTelCc($telcc)
          ->setAddress1((string)$wc->RegistrantAddress1)
          ->setCity((string)$wc->RegistrantCity)
          ->setCountry((string)$wc->RegistrantCountry)
          ->setState((string)$wc->RegistrantStateProvince)
          ->setZip((string)$wc->RegistrantPostalCode)
          ->setId(000000);

        if(isset($wc->RegistrantAddress2)) {
            $c->setAddress2((string)$wc->RegistrantAddress2);
        }

        $domain->setContactRegistrar($c);

        return $domain;
    }

    public function deleteDomain(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('Registrar does not support domain removal.');
    }

    public function registerDomain(Registrar_Domain $domain)
    {
    	//registering user
    	if (!$this->_addCustomer($domain)) {
    		throw new Registrar_Exception('Can\'t create user');
    	}
        $c = $domain->getContactRegistrar();
        $params = array(
            'command'                       =>  'Purchase',
            'SLD'                           =>  $domain->getSld(),
            'TLD'                           =>  $domain->getTld(),
            'AllowQueuing'                  =>  0,									//Order fails if can't be processed at once
            'NumYears'                      =>  $domain->getRegistrationPeriod(),
        );

        $types = array('Registrant', 'Tech', 'Admin', 'AuxBilling');
        foreach ($types as $type) {
        	$params[$type.'FirstName']			=	$c->getFirstName();
        	$params[$type.'LastName']			=	$c->getLastName();
        	$params[$type.'JobTitle']			=	'n/a';
        	$params[$type.'Address1']			=	$c->getAddress1();
        	$params[$type.'City']				=	$c->getCity();
        	$params[$type.'StateProvinceChoice']=	'S';						//S = state; P = province
        	$params[$type.'StateProvince']		=	$c->getState();
        	$params[$type.'PostalCode']			=	$c->getZip();
        	$params[$type.'Country']			=	$c->getCountry();
        	$params[$type.'EmailAddress']		=	$c->getEmail();
        	$params[$type.'Phone']				=	'+'.$c->getTelCc().'.'.$c->getTel();
        	$params[$type.'Fax']				=	'+'.$c->getFaxCc().'.'.$c->getFax();
        	
        	$optional_params[$type.'OrganizationName']	=	$c->getCompany();
        	$optional_params[$type.'Address2']			=	$c->getAddress2();
        }

        $params = array_merge($params, $optional_params);

        $i = 1;
        foreach($domain->getNameservers() as $nse) {
            $params['NS'.$i] = $nse->getHost();
            $i++;
        }

        $params = array_merge($params, $optional_params);
        $result = $this->_makeRequest($params);
        if (isset($result->RRPCode) && $result->RRPCode == 200) {
        	if (!$this->_moveDomainToAccount($domain)) {
        		throw new Registrar_Exception('Can\'t move domain to users account');
        	} else {
        		sleep(15);
        		return true;
        	}
        }
        
        return false;
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        $params = array (
        	'command'			=>	'SynchAuthInfo',
        	'SLD'				=>	$domain->getSld(),
        	'TLD'				=>	$domain->getTld(),
        	'EmailEPP'			=>	'True',						//Emails EPP key to user
        	'RunSynchAutoInfo'	=>	'True'
        );
        
        if ($this->_getDomainLock($domain)) {
        	if (!$this->_toggleDomainLock($domain, 1)) {
        		throw new Exception('EnomApiError: Can\'t unlock domain for transfer');
        	}
        }
        $result = $this->_makeRequest($params);
        if ($result->ErrCount == 0) return true;
        return false;
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $params = array(
            'command'   =>  'extend',
            'SLD'       =>  $domain->getSld(),
            'TLD'       =>  $domain->getTld(),
            'NumYears'  =>  1
        );

        $result = $this->_makeRequest($params);
        if (isset($result->RRPCode) && $result->RRPCode == 200) return true;
        return false;
    }
    
    public function modifyContact(Registrar_Domain $domain)
    {
		$c = $domain->getContactRegistrar();
		$params = array(
			'command'			=>	'Contacts',
			'sld'				=>	$domain->getSld(),
			'tld'				=>	$domain->getTld(),
		);	
		
		$optional_params = array();
		
		$types = array('Registrant', 'Tech', 'Admin', 'AuxBilling');
		foreach ($types as $type) {
			$params[$type.'FirstName']			=	$c->getFirstName();
			$params[$type.'LastName']			=	$c->getLastName();
			$params[$type.'OrganizationName']	=	$c->getCompany();
			$params[$type.'Address1']			=	$c->getAddress1();
			$params[$type.'City']				=	$c->getCity();
			$params[$type.'PostalCode']			=	$c->getZip();
			$params[$type.'Country']			=	$c->getCountry();
			$params[$type.'EmailAddress']		=	$c->getEmail();
			$params[$type.'Phone']				=	'+'.$c->getTelCc().'.'.$c->getTel();
			$params[$type.'Postal_code']		=	$c->getZip();
			
			$optional_params[$type.'StateProvinceChoice']	=	'S';				//S = State, P = Province
			$optional_params[$type.'StateProvince']			=	$c->getState();
			$optional_params[$type.'PhoneExt']				=	'';
			$optional_params[$type.'Fax']					=	'';
			$optional_params[$type.'Address2']				=	$c->getAddress2();
		}
		
		$params = array_merge($params, $optional_params);
		$result = $this->_makeRequest($params);
		if ($result->ErrCount == 0) {
			sleep(10);
			return true;	
		} else {
			return false;
		}
    }

    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $params = array(
        	'SLD'		=>	$domain->getSld(),
        	'TLD'		=>	$domain->getTld(),
        	'Service'	=>	'WPPS',
        	'command'	=>	'EnableServices',
        );
        $result = $this->_makeRequest($params);
        if ($result->ErrCount == 0) return true;
        else return false;
    }
    
    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $params = array(
        	'SLD'		=>	$domain->getSld(),
        	'TLD'		=>	$domain->getTld(),
        	'Service'	=>	'WPPS',
        	'command'	=>	'DisableServices',
        );
        $result = $this->_makeRequest($params);
        if ($result->ErrCount == 0) return true;
        else return false;
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

    public function isTestEnv()
    {
        return $this->_testMode;
    }

    /**
     * Api URL
     * @return string
     */
    private function _getApiUrl()
    {
        if($this->isTestEnv()) {
            return 'http://resellertest.enom.com/interface.asp';
        }
        return 'http://reseller.enom.com/interface.asp';
    }

    /**
     * Makes API call
     * @param array $params
     * @param string $type
     * @return bool
     * @throws Registrar_Exception
     */
    private function _makeRequest($params = array(), $type = 'xml')
    {
        $params = array_merge(array(
            'UID'   =>  $this->config['user'],
            'PW'    =>  $this->config['password']
        ), $params);

        $opts = array(
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_TIMEOUT         => 60,
            CURLOPT_USERAGENT       => 'http://fordnox.com',
            CURLOPT_URL             => $this->_getApiUrl().'?responseType='.$type,
        );

        foreach ($params as $key => $param)
        {
            if (is_array($param)) $param = implode(',',$param);
            if (strtolower($key) == 'tld' && substr($param, 0, 1) == '.') $param = substr($param, 1);
            if (isset($param) && $param != '') $opts[CURLOPT_URL] .= '&'.$key.'='.urlencode((string)$param);
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
            throw new Registrar_Exception('simpleXmlException');
        }

        if (isset($xml->ErrCount) && $xml->ErrCount > 0) {
            throw new Registrar_Exception(sprintf('EnomApiError: "%s"', implode(', ', (array)$xml->errors)), 100);
        }

        return $xml;
    }

    /**
     * 
     * Separates telephon from international calling code
     * @param string $tel
     * @return array|string
     */
    private function _separateTelephone($tel) {
        $tel = str_replace(array('+', '(', ')', ' '), '', $tel);
		$tel = explode('.', $tel);
        if (isset($tel[0]) && isset($tel[1])) {
        	return array(
        		'code' 	=>	$tel[0],
        		'tel'	=>	$tel[1]
        	);
        } else {
        	return $tel;
        }
    }
    
    private function _addCustomer(Registrar_Domain $d) {
    	//checking if user allready exists
    	if ($this->_checkSubAcountExists($d)) return true;
    	
    	//creating new user
    	$c = $d->getContactRegistrar();
    	$passw = $c->getPassword();
    	if (!$passw) $passw = substr(MD5($c->getEmail().rand(0, 1000000)), 0, 20);
    	$params = array(
    		'command'				=>	'CreateSubAccount',
    		'NewUID'				=>	$this->_getUsername($c->getEmail()),
    		'NewPw'					=>	$passw,
    		'ConfirmPw'				=>	$passw,
    		'RegistrantAdress1'		=>	$c->getAddress1(),
    		'RegistrantCity'		=>	$c->getCity(),
    		'RegistrantCountry'		=>	$c->getCountry(),
    		'RegistrantEmailAddress'=>	$c->getEmail(),
    		'RegistrantFirstName'	=>	$c->getFirstName(),
    		'RegistrantLastName'	=>	$c->getLastName(),
    		'RegistrantPhone'		=>	'+'.$c->getTelCc().'.'.$c->getTel()
    	);
    	
    	$optional_params = array(
    		'AuthQuestionType'					=>	'',
    		'AuthQuestionAnswer'				=>	'',
    		'RegistrantAddress2'				=>	$c->getAddress2(),
    		'RegistrantEmailAddress_Contact'	=>	'',
    		'RegistrantFax'						=>	'',
    		'RegistrantJobTitle'				=>	'',
    		'RegistrantOrganizationName'		=>	$c->getCompany(),
    		'RegistrantPostalCode'				=>	$c->getZip(),
    		'RegistrantStateProvince'			=>	$c->getState(),
    		'RegistrantStateProvinceChoice'		=>	'S',			//S = state, P = Province
    	);
    	
    	$params = array_merge($params, $optional_params);
    	$result = $this->_makeRequest($params);
    	if ($result->ErrCount == 0 && isset($result->NewAccount->Account)) return true;
    	else return false;
    }
    
    /**
     * Check if all required params are present, if not add default values
     * @param array $required_params - list of required params with default values
     * @param array $params - given params
     * @return array
     * @throws Registrar_Exception
     */
    private function _checkRequiredParams($required_params, $params)
    {
        foreach($required_params as $param => $value) {
            if(!isset($params[$param])) {
                $params[$param] = $value;
            }

            if(!is_bool($params[$param]) && empty($params[$param])) {
//                throw new Registrar_Exception(sprintf('Required param "%s" can not be blank', $param));
            }
        }

        return $params;
    }
    
    
    /**
     * 
     * Moves domain to another enom account
     * @param Registrar_Domain $d
     * @return boolean
     */
    private function _moveDomainToAccount(Registrar_Domain $d) {
    	$c = $d->getContactRegistrar();
    	$params = array(
    		'command'		=>	'PushDomain',
    		'SLD'			=>	$d->getSld(),
    		'TLD'			=>	$d->getTld(),
    		'AccountID'		=>	$this->_getUsername($c->getEmail()),
    		'PushContact'	=>	1
    	);
    	
    	$result = $this->_makeRequest($params);
    	
    	if ($result->ErrCount == 0) return true;
    	else return false;
    }
    
    /**
     * 
     * Generates eNom friendly username from email
     * @param string $email
     * @return string
     */
    private function _getUsername($email) {
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
     * Checks if SubAccount exists in reseller account
     * @param Registrar_Domain $d
     * @return bool
     */
    private function _checkSubAcountExists(Registrar_Domain $d) {
    	$c = $d->getContactRegistrar();
    	$email = $c->getEmail();
    	$username = $this->_getUsername($email);
    	$params = array(
    		'command'		=>	'getsubaccounts',
    		'startletter'	=>	$username
    	);
    	
    	$result = $this->_makeRequest($params);
    	if (isset($result->SubAccounts->SubAccount)) {
    		$subaccounts = $result->SubAccounts->SubAccount;
    		if (is_array($subaccounts)) {
    			foreach ($subaccounts as $subaccount) {
    				if (isset($subaccount->LoginID) && (string)$subaccount->LoginID == $username) return true;
    			}
    		} else {
    			if (isset($subaccounts->LoginID) && (string)$subaccounts->LoginID == $username) return true;
    		}
    	}
    	
    	return false;
    }
    
    /**
     * 
     * Gets info about privacy:
     * if privacy is allowed for this tld,
     * if privacy is purchased for this domain,
     * if privacy is enabled for this domain
     * @param Registrar_Domain $domain
     * @throws Registrar_Exception
     * @return array
     */
    private function _checkPrivacy(Registrar_Domain $domain) {
    	$params = array(
        	'command'		=>	'GetWPPSInfo',
        	'SLD'			=>	$domain->getSld(),
        	'TLD'			=>	$domain->getTld()
        );
        
        $result = $this->_makeRequest($params);
        if (isset($result->GetWPPSInfo)) {
        	$info = $result->GetWPPSInfo;
        	return array(
        		'allowed'	=>	(string)$info->WPPSAllowed,
        		'exists'	=>	(string)$info->WPPSExists,
        		'enabled'	=>	(string)$info->WPPSEnabled
        	);	
        } else { 
        	throw new Registrar_Exception('EnomApiError: Can\'t get domain privacy information');
        }
    }
	
    /**
     * 
     * Purchases privacy protection service for domain
     * @param Registrar_Domain $domain
     * @return bool
     */
    private function _purchasePrivacy(Registrar_Domain $domain) {
    	$params = array(
    		'command'		=>	'PurchaseServices',
    		'Service'		=>	'WPPS',
    		'SLD'			=>	$domain->getSld(),
    		'TLD'			=>	$domain->getTld(),
    	);
    	
    	$result = $this->_makeRequest($params);
    	if ($result->ErrCount == 0) return true;
    	else return false;
    }
    
    /**
     * 
     * Checks if domain is locked from transfering to another registrar
     * @param Registrar_Domain $domain
     * @return bool
     */
    private function _getDomainLock(Registrar_Domain $domain) {
    	$params = array(
    		'command'		=>	'GetRegLock',
    		'SLD'			=>	$domain->getSld(),
    		'TLD'			=>	$domain->getTld()
    	);
    	
    	$result = $this->_makeRequest($params);
    	if (isset($result->{'reg-lock'}) && $result->{'reg-lock'} == 1) return true;
    	return false;
    }
    
    /**
     * 
     * Toggles domain transfer lock
     * @param Registrar_Domain $domain
     * @param integer $lock
     * @return bool
     */
    private function _toggleDomainLock(Registrar_Domain $domain, $lock = 0) {
    	$params = array(
    		'command'			=>	'SetRegLock',
    		'TLD'				=>	$domain->getTld(),
    		'SLD'				=>	$domain->getSld(),
    		'UnlockRegistrar'	=>	$lock
    	);
    	
    	$result = $this->_makeRequest($params);
    	if ((string)$result->RegistrarLock == 'ACTIVE') return true;
    	return false;
    }
}