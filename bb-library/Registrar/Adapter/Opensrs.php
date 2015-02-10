<?php
class Registrar_Adapter_Opensrs extends Registrar_AdapterAbstract
{
    public $config = array(
        'user'   => null,
        'key' => null,
        'client_user' => null,
        'client_pass' => null
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
            throw new Registrar_Exception('Domain registrar "OpenSRS" is not configured properly. Please update configuration parameter "OpenSRS Username" at "Configuration -> Domain registration".');
        }

        if(isset($options['key']) && !empty($options['key'])) {
            $this->config['key'] = $options['key'];
            unset($options['key']);
        } else {
            throw new Registrar_Exception('Domain registrar "OpenSRS" is not configured properly. Please update configuration parameter "OpenSRS key" at "Configuration -> Domain registration".');
        }

        if(isset($options['client_user']) && !empty($options['client_user'])) {
            $this->config['client_user'] = $options['client_user'];
            unset($options['client_user']);
        } else {
            throw new Registrar_Exception('Domain registrar "OpenSRS" is not configured properly. Please update configuration parameter "OpenSRS client username" at "Configuration -> Domain registration".');
        }
        
        if(isset($options['client_pass']) && !empty($options['client_pass'])) {
            $this->config['client_pass'] = $options['client_pass'];
            unset($options['client_pass']);
        } else {
            throw new Registrar_Exception('Domain registrar "OpenSRS" is not configured properly. Please update configuration parameter "OpenSRS client password" at "Configuration -> Domain registration".');
        }

        //@todo
//        require_once 'Registrar/opensrs/openSRS_loader.php';
//        require_once 'Registrar/opensrs/spyc.php';
    }
    
    public static function getConfig()
    {
        return array(
            'label'     =>  'Manages domains on OpenSRS via API',
            'form'  => array(
                'user' => array('text', array(
                            'label' => 'OpenSRS Username', 
                            'description'=>'OpenSRS Username'
                        ),
                     ),
                'key' => array('password', array(
                            'label' => 'OpenSRS key', 
                            'description'=>'OpenSRS key',
                            'renderPassword'    =>  true, 
                        ),
                     ),
                'client_user' => array('text', array(
                            'label' => 'OpenSRS client username', 
                            'description'=>'The username of the registrant. For more information please visit www.opensrs.com'
                        ),
                     ),
                'client_pass' => array('password', array(
                            'label' => 'OpenSRS client password', 
                            'description'=>'The registrants password. For more information please visit www.opensrs.com',
                            'renderPassword'    =>  true,
                        ),
                     ),
            ),
        );
    }
    
    /**
     * Tells what TLDs can be registered via this adapter
     * @return array
     */
    public function getTlds()
    {
        return array(
            '.com', '.net', '.org', '.info', '.tel',
            '.biz', '.name', '.mobi', '.asia',
            '.co', '.me', '.tv', '.ws', '.xxx',
            '.at', '.au', '.be', '.bz', '.ca', '.cc',
            '.ch', '.de', '.dk', '.es', '.eu', '.fr', '.in',
            '.it', '.li', '.mx', '.nl', '.uk', '.us'
        );
    }

    
    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $data = array(
            'func' => 'lookupDomain',
            'data' => array(
                'domain' => $domain->getName(),
                'selected' => $domain->getTld()
            ),
            'user' => $this->config['user'],
            'key' => $this->config['key'],
            'host' => $this->_getApiUrl()
        );

        $result = $this->_process($data);

        if ($result)
            return ($result[0]['status'] == 'available');
        return false;
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        $cookie = $this->_getCookie($domain);

        $ns = array();
        foreach ($domain->getNameservers() as $nse)
        {
            $ns[] = $nse->getHost();
        }
        $nameservers = implode(',', $ns);

        $data = array(
            'func' => 'nsAdvancedUpdt',
            'data' => array(
                'domain' => $domain->getName(),
                'bypass' => '',
                'cookie' => $cookie,
                'op_type' => 'assign',
                'assign_ns' => $nameservers
            ),
            'user' => $this->config['user'],
            'key' => $this->config['key'],
            'host' => $this->_getApiUrl()
        );

        return ((bool) $this->_process($data));
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();
        $cookie = $this->_getCookie($domain);
        
        $data = array(
            'func' => 'provUpdateContacts',
            'data' => array(
                'domain' => $domain->getName(),
                'types' => 'owner,admin,billing,tech',
                'cookie' => $cookie
            ),
            'personal' => array(
                'first_name' => $c->getFirstName(),
                'last_name' => $c->getLastName(),
                'phone' => $c->getTelCc() . '.' . $c->getTel(),
                'fax' => $c->getFaxCc() . '.' . $c->getFax(),
                'email' => $c->getEmail(),
                'org_name' => $c->getCompany(),
                'address1' => $c->getAddress1(),
                'address2' => $c->getAddress2(),
                'address3' => $c->getAddress3(),
                'postal_code' => $c->getZip(),
                'city' => $c->getCountry(),
                'url' => '',
                'state' => $c->getState(),
                'country' => $c->getCountry(),
                'lang_pref' => 'EN'
            ),
            'user' => $this->config['user'],
            'key' => $this->config['key'],
            'host' => $this->_getApiUrl()
        );
        
        return ((bool) $this->_process($data));
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();

        $data = array(
            'func' => 'provSWregister',
            'data' => array(
                'auto_renew' => '0',
                'link_domains' => '0',
                'f_parkp' => 'Y',
                'custom_tech_contact' => '0',
                'domain' => $domain->getName(),
                'period' => '1',
                'reg_type' => 'transfer',
                'reg_username' => $this->config['client_user'],
                'reg_password' => $this->config['client_pass'],
                'custom_transfer_nameservers' => '0',
                'custom_nameservers' => '0'
            ),
            'personal' => array(
                'first_name' => $c->getFirstName(),
                'last_name' => $c->getLastName(),
                'phone' => $c->getTelCc() . '.' . $c->getTel(),
                'fax' => $c->getFaxCc() . '.' . $c->getFax(),
                'email' => $c->getEmail(),
                'org_name' => $c->getCompany(),
                'address1' => $c->getAddress1(),
                'address2' => $c->getAddress2(),
                'address3' => $c->getAddress3(),
                'postal_code' => $c->getZip(),
                'city' => $c->getCountry(),
                'url' => '',
                'state' => 'n/a',
                'country' => $c->getCountry(),
                'lang_pref' => 'EN'
            ),
            'user' => $this->config['user'],
            'key' => $this->config['key'],
            'host' => $this->_getApiUrl()
        );

        return ((bool) $this->_process($data));
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
        $cookie = $this->_getCookie($domain);

        $data = array(
            'func' => 'lookupGetDomain',
            'data' => array(
                'cookie' => $cookie,
                'bypass' => '',
                'type' => 'all_info',
            ),
            'user' => $this->config['user'],
            'key' => $this->config['key'],
            'host' => $this->_getApiUrl()
        );

        $result = $this->_process($data);

        if ($result)
            return $this->_createDomainObj($result['attributes'], $domain);
        return false;
    }

    public function deleteDomain(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('Registrar does not support domain removal.');
    }

    public function registerDomain(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();
        
        $data = array(
            'func' => 'provSWregister',
            'data' => array(
                'domain' => $domain->getName(),
                'custom_tech_contact' => '0',
                'custom_nameservers' => '1',
                'reg_username' => $this->config['client_user'],
                'reg_password' => $this->config['client_pass'],
                'reg_type' => 'new',
                'period' => $domain->getRegistrationPeriod(),
                'handle' => 'process'
            ),
            'personal' => array(
                'first_name' => $c->getFirstName(),
                'last_name' => $c->getLastName(),
                'phone' => $c->getTelCc() . '.' . $c->getTel(),
                'fax' => $c->getFaxCc() . '.' . $c->getFax(),
                'email' => $c->getEmail(),
                'org_name' => $c->getCompany(),
                'address1' => $c->getAddress1(),
                'address2' => $c->getAddress2(),
                'address3' => $c->getAddress3(),
                'postal_code' => $c->getZip(),
                'city' => $c->getCountry(),
                'url' => '',
                'state' => 'n/a',
                'country' => $c->getCountry(),
                'lang_pref' => 'EN'
            ),
            'user' => $this->config['user'],
            'key' => $this->config['key'],
            'host' => $this->_getApiUrl()
        );

        // add nameservers to the data array
        $i = 0;
        foreach ($domain->getNameservers() as $nameserver)
        {
            $data['data']['name' . $i] = $nameserver->getHost();
            $data['data']['sortorder' . $i] = $i;
            $i++;
        }

        return ((bool) $this->_process($data));
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $data = array(
            'func' => 'provRenew',
            'data' => array(
                'auto_renew' => '0',
			    'currentexpirationyear' => date('Y', $domain->getExpirationTime()),
			    'domain' => $domain->getName(),
			    'handle' => 'process',
			    'period' => '1'
            ),
            'user' => $this->config['user'],
            'key' => $this->config['key'],
            'host' => $this->_getApiUrl()
        );

        return ((bool) $this->_process($data));
    }

    public function togglePrivacyProtection(Registrar_Domain $domain)
    {
        $cookie = $this->_getCookie($domain);
        
        if ($this->_isPrivacyEnabled($domain)) $state = 'disable';
        else $state = 'enable';

        $data = array(
            'func' => 'provModify',
            'data' => array(
                'data' => 'whois_privacy_state',
                'state' => $state,  
                'cookie' => $cookie,
                'affect_domains' => '0'
            ),
            'user' => $this->config['user'],
            'key' => $this->config['key'],
            'host' => $this->_getApiUrl()
        );

        return ((bool) $this->_process($data));
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
     * Gets session.
     * @param Registrar_Domain $domain
     * @return string
     * @throws Registrar_Exception
     */
    private function _getCookie(Registrar_Domain $domain)
    {
        $data = array(
            'func' => 'cookieSet',
            'data' => array (
                'reg_username' => $this->config['client_user'],
                'reg_password' => $this->config['client_pass'],
                'domain' => $domain->getName()
            ),
            'user' => $this->config['user'],
            'key' => $this->config['key'],
            'host' => $this->_getApiUrl()
        );

        $result = $this->_process($data);

        if ($result)
            return $result['attributes']['cookie'];
        throw new Registrar_Exception($result['response_text']);
    }

    /**
     * Creates domain object from received data array.
     * @param array $result
     * @param Registrar_Domain $domain
     * @return Registrar_Domain 
     */
    private function _createDomainObj($result, $domain)
    {
        $contact = $result['contact_set']['admin'];
        $name = implode(' ', array($contact['first_name'], $contact['last_name']));

        $telCc = explode('.', $contact['phone']);
        $telCc = $telCc[0];

        $c = new Registrar_Domain_Contact();
		$c->setName($name)
            ->setEmail($contact['email'])
            ->setCompany($contact['org_name'])
            ->setTel($contact['phone'])
            ->setTelCc($telCc)
            ->setAddress1($contact['address1'])
            ->setAddress2($contact['address2'])
            ->setCity($contact['city'])
            ->setCountry($contact['country'])
            ->setState($contact['state'])
            ->setZip($contact['postal_code']);

        $nsList = array();
        foreach ($result['nameserver_list'] as $ns)
        {
            $n = new Registrar_Domain_Nameserver();
            $n->setHost($ns['name']);
            $nsList[] = $n;
        }
        
        $domain->setNameservers($nsList);
        $domain->setExpirationTime(strtotime($result['expiredate']));
        $domain->setRegistrationTime(strtotime($result['registry_createdate']));
        $domain->setContactRegistrar($c);
        $domain->setEpp($this->_getEPPcode($domain));
        return $domain;
    }

    /**
     * Checks whether privacy is enabled
     * @param Registrar_Domain $domain
     * @return bool
     */
    private function _isPrivacyEnabled(Registrar_Domain $domain)
    {
        $cookie = $this->_getCookie($domain);

        $data = array(
            'func' => 'lookupGetDomain',
            'data' => array (
                'type' => 'whois_privacy_state',
                'cookie' => $cookie,
                'bypass' => ''
            ),
            'user' => $this->config['user'],
            'key' => $this->config['key'],
            'host' => $this->_getApiUrl()
        );

        $result = $this->_process($data);

        return ($result && ($result['attributes']['state'] == 'enabled'));
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
        if ($this->isTestEnv())
            return 'horizon.opensrs.net';
        return 'rr-n1-tor.opensrs.net';
    }

    /**
     * Gets domain's EPP code.
     * @param Registrar_Domain $domain
     * @return string or bool
     */
    private function _getEPPcode(Registrar_Domain $domain)
    {
        $cookie = $this->_getCookie($domain);

        $data = array(
            'func' => 'lookupGetDomain',
            'data' => array(
                'cookie' => $cookie,
                'bypass' => '',
                'type' => 'domain_auth_info',
            ),
            'user' => $this->config['user'],
            'key' => $this->config['key'],
            'host' => $this->_getApiUrl()
        );

        $result = $this->_process($data);
        
        if ($result)
            return $result['attributes']['domain_auth_info'];
        return false;
    }

    /**
     * Process request.
     * @param array
     * @return array
     * @throws Registrar_Exception
     */
    private function _process($data)
    {
        try
        {
            $result = processOpensrs('array', $data)->resultFullRaw;
        }
        catch (Exception $e)
        {
            throw new Registrar_Exception($e->getMessage(), $e->getCode(), $e);
        }

        if (($result['response_code'] == 200) && ($result['is_success'] == 1))
            return $result;
        throw new Registrar_Exception($result['response_text']);
    }
}