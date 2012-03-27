<?php
class Registrar_Adapter_Planetdomain extends Registrar_AdapterAbstract
{
    public $config = array(
        'resellerid' => null,
        'user'   => null,
        'password' => null,
        
        'client_user' => null,
        'client_pass' => null,
    );

    public function __construct($options)
    {
        if (!extension_loaded('curl')) {
            throw new Registrar_Exception('CURL extension is not enabled');
        }

        if(isset($options['resellerid']) && !empty($options['resellerid'])) {
            $this->config['resellerid'] = $options['resellerid'];
            unset($options['resellerid']);
        } else {
            throw new Registrar_Exception('Domain registrar "PlanetDomain" is not configured properly. Please update configuration parameter "PlanetDomain Reseller ID" at "Configuration -> Domain registration".');
        }
        
        if(isset($options['user']) && !empty($options['user'])) {
            $this->config['user'] = $options['user'];
            unset($options['user']);
        } else {
            throw new Registrar_Exception('Domain registrar "PlanetDomain" is not configured properly. Please update configuration parameter "PlanetDomain Username" at "Configuration -> Domain registration".');
        }

        if(isset($options['password']) && !empty($options['password'])) {
            $this->config['password'] = $options['password'];
            unset($options['password']);
        } else {
            throw new Registrar_Exception('Domain registrar "PlanetDomain" is not configured properly. Please update configuration parameter "PlanetDomain Password" at "Configuration -> Domain registration".');
        }
        
        if(isset($options['client_user']) && !empty($options['client_user'])) {
            $this->config['client_user'] = $options['client_user'];
            unset($options['client_user']);
        } else {
            throw new Registrar_Exception('Domain registrar "PlanetDomain" is not configured properly. Please update configuration parameter "PlanetDomain Client Username" at "Configuration -> Domain registration".');
        }
        
        if(isset($options['client_pass']) && !empty($options['client_pass'])) {
            $this->config['client_pass'] = $options['client_pass'];
            unset($options['client_pass']);
        } else {
            throw new Registrar_Exception('Domain registrar "PlanetDomain" is not configured properly. Please update configuration parameter "PlanetDomain Client Password" at "Configuration -> Domain registration".');
        }
    }

    public static function getConfig()
    {
        return array(
            'label' => 'Manages domains on PlanetDomain via API',
            'form'  => array(
                'resellerid' => array('text', array(
                            'label' => 'PlanetDomain Reseller ID',
                            'description'=>'PlanetDomain Reseller ID',
                    ),
                 ),
                'user' => array('text', array(
                            'label' => 'PlanetDomain Username',
                            'description'=>'PlanetDomain Username',
                    ),
                 ),
                'password' => array('password', array(
                            'label' => 'PlanetDomain Password',
                            'description'=>'PlanetDomain Password',
                            'renderPassword' => true,
                    ),
                 ),
                
                'client_user' => array('text', array(
                            'label' => 'PlanetDomain Client Username',
                            'description'=>'PlanetDomain Client Username',
                    ),
                 ),
                'client_pass' => array('password', array(
                            'label' => 'PlanetDomain Client Password',
                            'description'=>'PlanetDomain Client Password',
                            'renderPassword' => true,
                    ),
                 ),
            ),
        );
    }

    public function getTlds()
    {
        return array(
            '.com', '.com.au', '.me.uk', '.co.nz', 
            '.net', '.net.au', '.us.com', '.net.nz', 
            '.org', '.org.au', '.uk.com', '.info', 
            '.mobi', '.asn.au', '.org.uk', '.us', 
            '.asia', '.id.au', '.tv', '.es', '.biz', 
            '.co.uk', '.eu',
        );
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $params = array(
            'domain.name' => $domain->getName(),
        );
        $result = $this->_request('domain.lookup', $params);
        
        return $result['domain.status'] == 'AVAILABLE';
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        $params = array(
            'domain.name' => $domain->getName(),
        );
        
        $i = 0;
        foreach ($domain->getNameservers() as $ns)
        {
            $params['ns.name.' . $i] = $ns->getHost();
            $params['ns.ip.' . $i++] = gethostbyname($ns->getHost());
        }
        $this->_request('domain.update.ns', $params);
        
        return true;
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();
        
        $params = array(
            'domain.name' => $domain->getName(),
        );
        $result = $this->_request('domain.info', $params);
        
        $params = array(
            'domain.name' => $domain->getName(),
            'user.id' => $result['domain.ownerid'],
            
            'user.firstname' => $c->getFirstName(),
            'user.lastname' => $c->getLastName(),
            'user.company' => $c->getCompany(),
            'user.address1' => $c->getAddress1(),
            'user.address2' => $c->getAddress2(),
            'user.suburb' => $c->getCity(),
            'user.postcode' => $c->getZip(),
            'user.state' => $c->getState(),
            'user.country' => $c->getCountry(),
            'user.phone' => $c->getTelCc() . '.' . $c->getTel(),
            'user.email' => $c->getEmail(),
        );
        $result = $this->_request('user.update', $params);
        
        return true;
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        $params = array(
            'domain.name' => $domain->getName(),
        );
        $result = $this->_request('domain.info', $params);
        
        $params = array(
            'domain.name' => $domain->getName(),
            'user.id' => $result['domain.ownerid'],
        );
        $result = $this->_request('domain.transfer', $params);
        
        return true;
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
        $params = array(
            'domain.name' => $domain->getName(),
        );
        $result = $this->_request('domain.info', $params);
        
        $params = array(
            'user.id' => $result['domain.ownerid'],
        );
        $user = $this->_request('user.info', $params);
        
        $tel = explode(".", $user['user.phone']);
        
        // Update domain
        $c = new Registrar_Domain_Contact();
        $c->setFirstName($user['user.firstname'])
          ->setLastName($user['user.lastname'])
          ->setEmail($user['user.email'])
          ->setCompany($user['user.company'])
          ->setTel($tel[1])
          ->setTelCc($tel[0])
          ->setAddress1($user['user.address1'])
          ->setAddress2($user['user.address2'])
          ->setCity($user['user.suburb'])
          ->setCountry($user['user.country'])
          ->setZip($user['user.postcode']);

        // Add nameservers
        $ns_list = array();
        for ($i = 0; $i < 5; $i++) {
            if (!isset($result['ns.name.' . $i])) {
                $n = new Registrar_Domain_Nameserver();
                $n->setHost($result['ns.name.' . $i]);
                $ns_list[] = $n;
            }
        }

        $domain->setNameservers($ns_list);
        $domain->setExpirationTime(strtotime($result['domain.expirydate']));
        $domain->setRegistrationTime(strtotime($result['domain.createddate']));
//        $domain->setPrivacyEnabled($privacy);
        $domain->setEpp($this->_getEPP($domain));
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
        
        // Register new user and get id
        $params = array(
            'user.firstname' => $c->getFirstName(),
            'user.lastname' => $c->getLastName(),
            'user.address1' => $c->getAddress1(),
            'user.address2' => $c->getAddress2(),
            'user.suburb' => $c->getCity(),
            'user.postcode' => $c->getZip(),
            'user.state' => $c->getState(),
            'user.country' => $c->getCountry(),
            'user.phone' => '+' . $c->getTelCc() . '.' . $c->getTel(),
            'user.email' => $c->getEmail(),
            'user.username' => $this->config['client_user'],
            'user.password' => $this->config['client_pass'],
        );
        $result = $this->_request('user.add', $params);
            
        $params = array(
            'domain.name' => $domain->getName(),
            'owner.id' => $result['user.id'],
            'tech.id' => $result['user.id'],
            'admin.id' => $result['user.id'],
            'billing.id' => $result['user.id'],
            'register.period' => $domain->getRegistrationPeriod(),
        );
        
        $i = 0;
        foreach ($domain->getNameservers() as $ns)
        {
            $params['ns.name.' . $i] = $ns->getHost();
            $params['ns.ip.' . $i++] = gethostbyname($ns->getIp());
        }
        
        if ($domain->getTld() == 'us')
        {
            $params['us.intended_use'] = P3;
            $params['us.nexus_category'] = C11;  
        }
        
        if ($domain->getTld() == 'com.au')
        {
            $params['au.org.type'] = 'NONPROFIT';
            $params['au.registrant.name'] = $domain->getContactRegistrar()->getName();
        }
        
        $this->_request('domain.register', $params);
        
        return true;
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $params = array(
            'domain.name' => $domain->getName(),
            'register.period' => $domain->getRegistrationPeriod(),
        );
        $this->_request('domain.renew', $params);
        
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
        // Set authentication params
        $params['operation'] = $cmd;
        $params['admin.username'] = $this->config['user'];
        $params['admin.password'] = $this->config['password'];
        $params['reseller.id'] = $this->config['resellerid'];

        $curl_opts = array(
            CURLOPT_URL => $this->_getApiUrl(),
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => http_build_query($params),
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
        
        $return = array();
        $splitResult = explode("\n", $result);
        foreach ($splitResult as $str) {
            if (strlen($str)) {
                $splitStr = explode("=", trim($str));  
                $return[$splitStr[0]] = $splitStr[1];
            }
        }
        
        $this->getLog()->debug(print_r($params, true));
        $this->getLog()->debug(print_r($return, true));
        
        if ($return['success'] == 'FALSE') {
            throw new Registrar_Exception($return['error.desc.0']);
        }
        
        return $return;
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
            return 'https://test-www.planetdomain.com/servlet/TLDServlet';
        return 'https://api.planetdomain.com/servlet/TLDServlet';
    }
    
    private function _getEPP(Registrar_Domain $domain) 
    {
        $params = array(
            'domain.name' => $domain->getName(),
            'user.id' => $this->config['client_user'],
        );
        $result = $this->_request('domain.authcode', $params);
        
        return $result['domain.password'];
    }
}