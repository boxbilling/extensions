<?php

class Registrar_Adapter_TPPInternet extends Registrar_AdapterAbstract
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
            throw new Registrar_Exception('Domain registrar "TPP Internet" is not configured properly. Please update configuration parameter "TPP Internet Username" at "Configuration -> Domain registration".');
        }

        if(isset($options['password']) && !empty($options['password'])) {
            $this->config['password'] = $options['password'];
            unset($options['password']);
        } else {
            throw new Registrar_Exception('Domain registrar "TPP Internet" is not configured properly. Please update configuration parameter "TPP Internet Password" at "Configuration -> Domain registration".');
        }
    }

    public static function getConfig()
    {
        return array(
            'label'     =>  'Manages domains on TPP Internet via API',
            'form'  => array(
                'user' => array('text', array(
                            'label' => 'TPP Internet Username',
                            'description'=>'TPP Internet Username'
                        ),
                     ),
                'password' => array('password', array(
                            'label' => 'TPP Internet password',
                            'description'=>'TPP Internet password',
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
            'com', 'net', 'org', 'biz', 'info',
            'eu', 'asia', 'co', 'jobs', 'mobi',
            'tel', 'travel', 'com.au', 'net.au',
            'org.au', 'asn.au', 'id.au', 
            'co.uk', 'org.uk', 'me.uk', 
        );
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getSld(),
            'suffixes' => $domain->getTld(),
        );
        $result = $this->_request('availability/check', $params);
        
        return isset($result['available'])
                && ($result['available'] == $domain->getName());
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
        $params = array(
            'TransactionType' => 'DELEGATION',
            'DomainName' => $domain->getName(),
        );
        
        $i = 1;
        foreach ($domain->getNameservers() as $ns) {
            $params['NameServer' . $i] = $ns->getHost();
            $params['NameServer' . $i++ . 'IP'] = gethostbyname($ns->getHost());
        }
        $result = $this->_request('updates/domainupdate', $params);
        
        return true;
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function modifyContact(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();
        
        $params = array(
            'TransactionType' => 'CONTACT_UPDATE',
            'DomainName' => $domain->getName(),
            
            'RegistrantFirstName' => $c->getFirstname(),
            'RegistrantLastName' => $c->getLastname(),
            'RegistrantCompanyName' => $c->getCompany(),
            'RegistrantEmail' => $c->getEmail(),
            'RegistrantPhone' => '+' . $c->getTelcc() . '.' . $c->getTel(),
            'RegistrantAddressLine1' => $c->getAddress1(),
            'RegistrantAddressLine2' => $c->getAddress2(),
            'RegistrantSuburb' => $c->getCity(),
            'RegistrantPostcode' => $c->getZip(),
            'RegistrantState' => $c->getState(),
            'RegistrantCountry' => $c->getCountry(),
        );
        $result = $this->_request('updates/domainupdate', $params);
        
        return true;
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function transferDomain(Registrar_Domain $domain)
    {
        $params = array(
            'RequestType' => 'T',
            'DomainName' => $domain->getName(),
        );
        $result = $this->_request('orderrequest', $params);
        
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
            'DomainName' => $domain->getName(),
        );
        $result = $this->_request('info/DomainInfo', $params);

        $tel = explode(".", $result['RegistrantPhone']);
        
        $c = new Registrar_Domain_Contact();
        $c->setName($result['RegistrantFirstName'] . ' ' . $result['RegistrantLastName'])
          ->setEmail($result['RegistrantEmail'])
          ->setTel($tel[1])
          ->setTelCc($tel[0])
          ->setAddress1($result['RegistrantAddress1'])
          ->setAddress2($result['RegistrantAddress2'])
          ->setCity($result['RegistrantSuburb'])
          ->setCountry($result['RegistrantCountry'])
          ->setState($result['RegistrantState'])
          ->setZip($result['RegistrantPostcode']);

        // Add nameservers
        $ns_list = array(); 
        for ($i = 0; $i < 10; $i++) {
            $n = new Registrar_Domain_Nameserver();
            if ($i == 0) {
                $n->setHost($result['PrimaryNameServer']);
            } else {
                $n->setHost($result['SecondaryNameServer' . $i]);
            }
            $ns_list[] = $n;
            
            if (!isset($result['SecondaryNameServer' . $i + 1])) {
                break;
            }
        }

        $domain->setNameservers($ns_list);
        $domain->setExpirationTime(strtotime($result['ExpiryDate']));
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

        $params = array(
            'DomainName' => $domain->getName(),
            'RequestType' => 'R',
            'Period' => $domain->getRegistrationPeriod(),
            'RegistrantFirstName' => $c->getFirstname(),
            'RegistrantLastName' => $c->getLastname(),
            'RegistrantCompanyName' => $c->getCompany(),
            'RegistrantEmail' => $c->getEmail(),
            'RegistrantPhone' => '+' . $c->getTelcc() . '.' . $c->getTel(),
            'RegistrantAddressLine1' => $c->getAddress1(),
            'RegistrantAddressLine2' => $c->getAddress2(),
            'RegistrantSuburb' => $c->getCity(),
            'RegistrantPostcode' => $c->getZip(),
            'RegistrantState' => $c->getState(),
            'RegistrantCountry' => $c->getCountry(),
        );
        
        $i = 0;
        foreach ($domain->getNameservers() as $ns) {
            if ($i == 0) {
                $params['PrimaryNS'] = $ns->getHost();
                $params['PrimaryNSIP'] = gethostbyname($ns->getHost());
            } else {
                $params['SecondaryNS' . $i] = $ns->getHost();
                $params['SecondaryNS' . $i . 'IP'] = gethostbyname($ns->getHost());
            }
            $i++;
        }

        $this->_request('orderrequest', $params);
        
        return true;
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function renewDomain(Registrar_Domain $domain)
    {
        $params = array(
            'TransactionType' => 'RENEWAL',
            'DomainName' => $domain->getName(),
            'Period' => 1,
        );
        $result = $this->_request('updates/domainupdate', $params);
        
        return true;
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('TPPInternet Registrar does not support Privacy protection.');
    }
    
    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('TPPInternet Registrar does not support Privacy protection.');
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

    private function _request($cmd, $params)
    {
        $params['ClientID'] = $this->config['user'];
        $params['ClientPassword'] = $this->config['password'];
        
        $url = $this->_getApiUrl() . $cmd  . '.php' . '?' . http_build_query($params);
                
        $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $result = curl_exec($ch);
        
        $this->getLog()->debug($url);
        $this->getLog()->debug(print_r($result, true));
        
        $matches = array();
        if (preg_match('/1170#<br>#Description:#<br>#(.*?)#/i', $result, $matches)) {
            throw new Registrar_Exception($matches[1]);
        }

        return $this->_parseResponse($result);
    }
    
    private function _parseResponse($result) {
        $return = array();
        $result = str_replace(' ', '', $result);
        $result = explode("<br>", $result);
        
        foreach ($result as $r) {
            $matches = array();
            if (preg_match('/([\w\d]+):([^<#\n]+)/', $r, $matches)) {
                $return[$matches[1]] = $matches[2];
            }
        }

        return $return;
    }

    private function _getApiUrl()
    {
        if ($this->isTestEnv()) {
            return 'https://ssl.twoplums.com.au/tppautomation/test/';
        }
        return 'https://ssl.twoplums.com.au/tppautomation/production/';
    }

    public function isTestEnv()
    {
        return $this->_testMode;
    }
}