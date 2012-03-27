<?php
class Registrar_Adapter_CNic extends Registrar_AdapterAbstract
{
    public $config = array(
        'user'   => null,
        'password' => null
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
            throw new Registrar_Exception('Domain registrar "CentralNIC" is not configured properly. Please update configuration parameter "CentralNIC Username" at "Configuration -> Domain registration".');
        }

        if(isset($options['password']) && !empty($options['password'])) {
            $this->config['password'] = $options['password'];
            unset($options['password']);
        } else {
            throw new Registrar_Exception('Domain registrar "CentralNIC" is not configured properly. Please update configuration parameter "CentralNIC password" at "Configuration -> Domain registration".');
        }
        //@todo
        //require_once 'Registrar/CNic/Toolkit.php';
    }

    public static function getConfig()
    {
        return array(
            'label'     =>  'Manages domains on CentralNIC via API',
            'form'  => array(
                'user' => array('text', array(
                            'label' => 'CentralNIC Username',
                            'description'=>'CentralNIC Username'
                          ),
                 ),
                'password' => array('password', array(
                            'label' => 'CentralNIC password',
                            'description'=>'CentralNIC password',
                            'renderPassword'    =>  true,
                         ),
                 ),
            ),
        );
    }

    public function getTlds()
    {
        $query = new CNic_Toolkit('suffixes');
        return $this->_request($query)->suffixes();
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $query = new CNic_Toolkit('search', $domain->getSld());

        $query->set('suffixlist', array($domain->getTld()));

        return !$this->_request($query)->is_registered($domain->getTld());
    }

    public function isDomainCanBeTransfered(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Checking if domain can be transfered: ' . $domain->getName());
        return true;
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        $query = new CNic_Toolkit(
            'modify',           
            $domain->getName(),
            1,               
            $this->config['user'],
            $this->config['password']
        );

        $nsList = array();
        $nsList[] = $domain->getNs1();
        $nsList[] = $domain->getNs2();
        $nsList[] = $domain->getNs3();
        $nsList[] = $domain->getNs4();

        $query->set('dns', array(
            'drop'    => 'all',
            'add'    => $nsList
        ));

        return (bool) $this->_request($query);
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        $query = new CNic_Toolkit(
            'modify_handle',        
            1,                
            $this->config['user'],
            $this->config['password']
        );

        $userid = $this->_getHandleId($domain);
        $c = $domain->getContactRegistrar();

        $query->set('handle',    $userid);
        $query->set('name',    $c->getName());
        $query->set('company',    $c->getCompany());
        $query->set('street1',    $c->getAddress1());
        $query->set('street2',    $c->getAddress2());
        $query->set('city',    $c->getCity());
        $query->set('sp',    $c->getCountry());
        $query->set('postcode',    $c->getZip());
        $query->set('country',    $c->getCountry());
        $query->set('phone',    $c->getTelCc() . '.' . $c->getTel());
        $query->set('fax',    $c->getFaxCc() . '.' . $c->getFax());
        $query->set('email',    $c->getEmail());

        return (bool) $this->_request($query);
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        $query = new CNic_Toolkit(
            'start_transfer',    
            1,               
            $this->config['user'],
            $this->config['password']
        );

        $query->set('domains', array($domain->getName()));
        $query->set('authinfo', array($this->_getEpp($domain)));

        return (bool) $this->_request($query);
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
        $query = new CNic_Toolkit('whois', $domain->getName());
        
        $response = $this->_request($query);

        return $this->_createDomainObj($response, $domain);
    }

    public function deleteDomain(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('Registrar does not support domain removal.');
    }

    public function registerDomain(Registrar_Domain $domain)
    {
        $userid = $this->_createHandle($domain);

        $query = new CNic_Toolkit(
            'register',            
            $domain->getName(),       
            1,               
            $this->config['user'],         
            $this->config['password']           
        );

        $nsList = array();
        $nsList[] = $domain->getNs1();
        $nsList[] = $domain->getNs2();
        $nsList[] = $domain->getNs3();
        $nsList[] = $domain->getNs4();

        $query->set('registrant', 'John Doe, Example Company Ltd.');
        $query->set('chandle', $userid);
        $query->set('thandle', $userid);
        $query->set('bhandle', $userid);
        $query->set('ahandle', $userid);
        $query->set('dns', $nsList);
        $query->set('period', $domain->getRegistrationPeriod());

        return (bool) $this->_request($query);
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $query = new CNic_Toolkit(
            'issue_renewals',        
            1,            
            $this->config['user'],
            $this->config['password']
        );

        $query->set('period', $domain->getRegistrationPeriod());
        $query->set('domains', array($domain->getName()));

        return (bool) $this->_request($query);
    }

    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $query = new CNic_Toolkit('whois', $domain->getName());

        $contact = $this->_request($query)->response('chandle');

        // Request for privacy settings change
        $query = new CNic_Toolkit(
            'modify_handle',
            1,
            $this->config['user'],
            $this->config['password']
        );

        $query->set('handle', $contact['userid']);
        $query->set('visible', 1);

        return (bool) $this->_request($query);
    }

    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $query = new CNic_Toolkit('whois', $domain->getName());

        $contact = $this->_request($query)->response('chandle');

        // Request for privacy settings change
        $query = new CNic_Toolkit(
            'modify_handle',        
            1,               
            $this->config['user'],           
            $this->config['password']            
        );

        $query->set('handle', $contact['userid']);
        $query->set('visible', 0);

        return (bool) $this->_request($query);
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
     * Creates domain object from received data array.
     * @param array $result
     * @param Registrar_Domain $domain
     * @return Registrar_Domain
     */
    private function _createDomainObj($response, Registrar_Domain $domain)
    {
        $contact = $response->response('chandle');
        $nameservers = $response->response('dns');
        $privacy = false;
        // If name doesn't exist, that means privacy is on, so we need to
        // request contact details separately
        if (!array_key_exists('name', $contact))
        {
            $contact = $this->_getHandleInfo($contact['userid']);
            $privacy = true;
        }

        $c = new Registrar_Domain_Contact();
		$c->setName($contact['name'])
            ->setEmail($contact['email'])
            ->setCompany($contact['company'])
            ->setTel($contact['phone'])
            ->setFax($contact['fax'])
            ->setAddress1($contact['street1'])
            ->setAddress2($contact['street2'])
            ->setCity($contact['city'])
            ->setCountry($contact['country'])
            ->setZip($contact['postcode']);

        $domain->setContactRegistrar($c);
        $domain->setRegistrationTime($response->response('created'));
        $domain->setExpirationTime($response->response('expires'));
        $domain->setPrivacyEnabled($privacy);
        $domain->setEpp($this->_getEpp($domain));

        return $domain;
    }

    /**
     * Creates contact handle and returns its id.
     * @param Registrar_Domain $domain
     * @return string
     */
    private function _createHandle(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();

        $query = new CNic_Toolkit(
            'create_handle',
            1,
            $this->config['user'],
            $this->config['password']
        );

        $params = array(
            'name'     => $c->getName(),
            'company'  => $c->getCompany(),
            'street1'  => $c->getAddress1(),
            'street2'  => $c->getAddress2(),
            'street3'  => $c->getAddress3(),
            'city'     => $c->getCity(),
            'sp'       => $c->getCountry(),
            'postcode' => $c->getZip(),
            'country'  => $c->getCountry(),
            'phone'    => $c->getTelCc() . $c->getTel(),
            'fax'      => $c->getFaxCc() . $c->getFax(),
            'email'    => $c->getEmail(),
        );

        $query->set('handle', $params);
        $query->set('visible', 1);

        return $this->_request($query)->handle();
    }

    /**
     * Gets and formats information of the handle.
     * @param string $userid
     * @return array $contact
     */
    private function _getHandleInfo($userid)
    {
        $query = new CNic_Toolkit(
            'handle_info',       
            1,            
            $this->config['user'],
            $this->config['password']
        );
        
        $query->set('visible', 0);
        $query->set('handle', $userid);

        $response = $this->_request($query);

        $contact = array();
        foreach (array('name', 'company', 'address', 'street1',
                'street2', 'street3', 'city', 'sp', 'postcode',
                'country', 'tel', 'fax', 'email') as $key)
        {
            $contact[$key] = $response->response($key);
        }

        return $contact;
    }

    /**
     * Gets userid (handle) of a certain domain's contact.
     * @param Registrar_Domain $domain
     * @return string
     */
    private function _getHandleId(Registrar_Domain $domain)
    {
        $query = new CNic_Toolkit('whois', $domain->getName());

        $contact = $this->_request($query)->response('chandle');

        return $contact['userid'];
    }

    /**
     * Gets transfer (authcode) of the domain.
     * @param Registrar_Domain $domain
     * @return string
     */
    private function _getEpp(Registrar_Domain $domain)
    {
        $query = new CNic_Toolkit(
            'auth_info',
            1,
            $this->config['user'],
            $this->config['password']
        );

        $query->set('domain', $domain->getName());

        return $this->_request($query)->response('authcode');
    }

    /**
     * Performs the request.
     * @param CNic_Toolkit $query
     * @return string
     * @throws Registrar_Exception
     */
    private function _request($query)
    {
        if ($this->isTestEnv())
            $query->set('test', 1);

        $response = $query->execute();
        
        if ($response->is_success())
            return $response;
        throw new Registrar_Exception($response->response('message'));
    }
}