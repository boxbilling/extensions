<?php
class Registrar_Adapter_Gandi extends Registrar_AdapterAbstract
{
    public $config = array(
        'user'   => null,
        'password' => null
    );
    
    private $_s = null;

    public function __construct($options)
    {
        if (!extension_loaded('curl')) {
            throw new Registrar_Exception('CURL extension is not enabled');
        }
        
        if(isset($options['user']) && !empty($options['user'])) {
            $this->config['user'] = $options['user'];
            unset($options['user']);
        } else {
            throw new Registrar_Exception('Domain registrar "Gandi" is not configured properly. Please update configuration parameter "Gandi Username" at "Configuration -> Domain registration".');
        }

        if(isset($options['password']) && !empty($options['password'])) {
            $this->config['password'] = $options['password'];
            unset($options['password']);
        } else {
            throw new Registrar_Exception('Domain registrar "Gandi" is not configured properly. Please update configuration parameter "Gandi Password" at "Configuration -> Domain registration".');
        }
    }

    public static function getConfig()
    {
        return array(
            'label' => 'Manages domains on Gandi via API',
            'form'  => array(
                'user' => array('text', array(
                            'label' => 'Gandi Username',
                            'description'=>'Gandi Username',
                    ),
                 ),
                'password' => array('password', array(
                            'label' => 'Gandi Password',
                            'description'=>'Gandi Password',
                            'renderPassword' => true,
                    ),
                 ),
            ),
        );
    }

    public function getTlds()
    {
        return $this->_request('tld_list', array($this->_getSession()));
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        if (($domain->getTld() == 'pl') || ($domain->getTld() == 'in')) {
            throw new Registrar_Exception('Cannot check availablity of this TLD using Gandi api');
        }
        
        $params = array(
            $this->_getSession(),    
            array(
                $domain->getName(),
            ),
        );
        $result = $this->_request('domain_available', $params);

        return ($result[$domain->getName()] == 1);
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        $ns_list = array();
        foreach ($domain->getNameservers() as $ns)
            $ns_list[] = $ns->getHost();
        
        $params = array(
            $this->_getSession(),
            $domain->getName(),
            $ns_list,
        );
        $this->_request('domain_ns_set', $params);
        
        return true;
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        $params = array(
            $this->_getSession(),
            $domain->getName(),
        );
        $result = $this->_request('domain_info', $params);
        
        $c = $domain->getContactRegistrar();
        
        $params = array(
            $this->_getSession(),
            $result['owner_handle'],
            new Zend_XmlRpc_Value_Struct(
                    array(
                        'address' => $c->getAddress1(),
                        'zipcode' => $c->getZip(),
                        'city' => $c->getCity(),
                        'country' => $c->getCountry(),
                        'phone' => '+' . $c->getTelcc() . '.' . $c->getTel(),
                        'email' => $c->getEmail(),
                    )
            ),
        );
        $this->_request('contact_update', $params);
        
        return true;
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();

        $handle_id = $this->_createHandle($domain, true);
        
        $params = array(
            $this->_getSession(),
            $domain->getName(),
        );
        $result = $this->_request('domain_info', $params);
        
        $ns_list = array();
        foreach ($domain->getNameservers() as $ns)
            $ns_list[] = (string) $ns->getHost();
        
        $params = array(
            $this->_getSession(), 
            $domain->getName(), 
            $handle_id, 
            $handle_id, 
            $handle_id, 
            $handle_id, 
            $ns_list, 
            $result['authorization_code'],
        );

        $this->_request('domain_transfer_in', $params);
        
        return true;
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
        // Get Domain info
        $params = array(
            $this->_getSession(),
            $domain->getName(),
        );
        $result = $this->_request('domain_info', $params);
        
        // Get contact info
        $params = array(
            $this->_getSession(),
            $result['owner_handle'],
        );
        $contact = $this->_request('contact_info', $params);

        // Get nameservers
        $params = array(
            $this->_getSession(),
            $domain->getName(),
        );
        $nss = $this->_request('domain_ns_list', $params);
        
        $tel = explode(".", $contact['phone']);
        $tel[0] = str_replace('+', '', $tel[0]);
        
        $c = new Registrar_Domain_Contact();
        $c->setFirstName($contact['firstname'])
          ->setLastName($contact['lastname'])
          ->setEmail($contact['email'])
          ->setTel($tel[1])
          ->setTelCc($tel[0])
          ->setAddress1($contact['address'])
          ->setCity($contact['city'])
          ->setCountry($contact['country'])
          ->setZip($contact['zipcode']);

        // Add nameservers
        $ns_list = array();
        foreach ($nss as $ns)
        {
            $n = new Registrar_Domain_Nameserver();
            $n->setHost($ns);
            $ns_list[] = $n;
        }
        
        $domain->setNameservers($ns_list);
        $domain->setExpirationTime(strtotime($result['registry_expiration_date']));
        $domain->setRegistrationTime(strtotime($result['registry_creation_date']));
        $domain->setEpp($result['authorization_code']);
        $domain->setPrivacyEnabled($contact['whois_obfuscated']);
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

        $handle_id = $this->_createHandle($domain, true);
        
        $ns_list = array();
        foreach ($domain->getNameservers() as $ns)
            $ns_list[] = $ns->getHost();
        
        $params = array(
            $this->_getSession(),
            $domain->getName(),
            $domain->getRegistrationPeriod(),
            $handle_id,
            $handle_id,
            $handle_id,
            $handle_id,
            $ns_list,
        );
        $this->_request('domain_create', $params);
        
        return true;
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $params = array(
            $this->_getSession(),
            $domain->getName(),
            1
        );
        $this->_request('domain_renew', $params);
        
        return true;
    }

    public function togglePrivacyProtection(Registrar_Domain $domain)
    {
        // Get Domain info
        $params = array(
            $this->_getSession(),
            $domain->getName(),
        );
        $result = $this->_request('domain_info', $params);
        
        // Get contact info
        $params = array(
            $this->_getSession(),
            $result['owner_handle'],
        );
        $contact = $this->_request('contact_info', $params);
        
        $handle_id = $this->_createHandle($domain, !$contact['whois_obfuscated']);
        
        $params = array(
            $this->_getSession(),
            $domain->getName(),
            $handle_id,
            $handle_id,
            $handle_id,
            $handle_id,
        );
        $this->_request('domain_change_owner', $params);
        
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
        $client = new Zend_XmlRpc_Client($this->_getApiUrl());
        try {
            $result = $client->call(
                    $cmd, 
                    $params
            );
        } catch (Zend_XmlRpc_Client_HttpException $e) {
            throw new Registrar_Exception($e->getMessage());
        }
        
        $this->getLog()->debug($cmd);
        $this->getLog()->debug(print_r($params, true));
        $this->getLog()->debug(print_r($result, true));
        
        return $result;
	}
    
    /**
     * Logins and gets session string.
     * @return string
     */
    private function _getSession()
    {
        if ($this->_s) {
            return $this->_s;
        }
        
        $params = array(
            $this->config['user'],
            $this->config['password'],
            new Zend_XmlRpc_Value_Boolean(false)
        );
        
        return $this->_s = $this->_request('login', $params);
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
            return 'https://api.ote.gandi.net/xmlrpc/';
        }
        return 'https://rpc.gandi.net/xmlrpc/2.0/';
    }
    
    /**
     * Creates new contact handle.
     * @param Registrar_Domain $domain
     * @param bool $privacy
     * @return int
     */
    private function _createHandle(Registrar_Domain $domain, $privacy)
    {
        $c = $domain->getContactRegistrar();
        
        // Create contact
        $params = array(
            $this->_getSession(),
            'individual',
            $c->getFirstName(),
            $c->getLastName(),
            $c->getAddress1(),
            $c->getZip(),
            $c->getCity(),
            $c->getCountry(),
            '+' . $c->getTelCc() . '.' . $c->getTel(),
            $c->getEmail(),
            new Zend_XmlRpc_Value_Struct(array('whois_obfuscated' => new Zend_XmlRpc_Value_Boolean((bool) $privacy))),
        );
        
        return $this->_request('contact_create', $params);
    }
}