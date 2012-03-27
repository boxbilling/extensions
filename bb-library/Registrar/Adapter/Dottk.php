<?php
class Registrar_Adapter_Dottk extends Registrar_AdapterAbstract
{
    public $config = array(
        'email'   => null,
        'password' => null
    );

    public function __construct($options)
    {
        if (!extension_loaded('curl')) {
            throw new Registrar_Exception('CURL extension is not enabled');
        }

        if(isset($options['email']) && !empty($options['email'])) {
            $this->config['email'] = $options['email'];
            unset($options['email']);
        } else {
            throw new Registrar_Exception('Domain registrar "dotTK" is not configured properly. Please update configuration parameter "dotTK email" at "Configuration -> Domain registration".');
        }

        if(isset($options['password']) && !empty($options['password'])) {
            $this->config['password'] = $options['password'];
            unset($options['password']);
        } else {
            throw new Registrar_Exception('Domain registrar "dotTK" is not configured properly. Please update configuration parameter "dotTK password" at "Configuration -> Domain registration".');
        }
    }

    public static function getConfig()
    {
        return array(
            'label' => 'Manages domains on dotTK via API',
            'form'  => array(
                'email' => array('text', array(
                            'label' => 'dotTK email',
                            'description'=>'dotTK email',
                    ),
                 ),
                'password' => array('password', array(
                            'label' => 'dotTK password',
                            'description'=>'dotTK password',
                            'renderPassword' => true,
                    ),
                 ),
            ),
        );
    }

    public function getTlds()
    {
        return array(
            '.tk',
        );
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $params = 'domainname=' . $domain->getName();
        
        $result = $this->_request('availability_check', $params);
        
        return ($result->partner_availability_check->status == 'DOMAIN AVAILABLE');
    }

    public function isDomainCanBeTransfered(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('Domain transfer checking is not implemented');
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        $params = 'domainname=' . $domain->getName();
        foreach ($domain->getNameservers() as $ns)
            $params .= '&nameserver=' . $ns->getHost();
        
        $result = $this->_request('modify', $params);
        
        return ($result->partner_modify->status == 'DOMAIN MODIFIED');
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        throw new Registrar_Exception("Can't modify contacts using dotTK API.");
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        throw new Registrar_Exception("Can't transfer domains using dotTK API.");
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
        $params = 'domainname=' . $domain->getName();
        $result = $this->_request('availability_check', $params);
        
        $nsList = array();
        foreach ($result->partner_availability_check->nameservers as $ns)
            $nsList[] = (string) $ns->hostname;
        
        $date = $result->partner_availability_check->expiration_date;
        $date_str = substr($date, 0, 4) . ' ' . substr($date, 4, 2) . ' '. substr($date, 6, 2);
        
        $domain->setExpirationTime(strtotime($date_str));
        $domain->setNameservers($nsList);
        //$domain->setPrivacyEnabled($privacy);
        //$domain->setEpp($result['transferauthinfo']);

        return $domain;
    }

    public function deleteDomain(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('Registrar does not support domain removal.');
    }

    public function registerDomain(Registrar_Domain $domain)
    {  
        $params = 'domainname=' . $domain->getName();
        $params .= '&enduseremail=' . $domain->getContactRegistrar()->getEmail();
        $params .= '&monthsofregistration=' . $domain->getRegistrationPeriod() * 12;
        foreach ($domain->getNameservers() as $ns)
            $params .= '&nameserver=' . $ns->getHost();

        $result = $this->_request('register', $params);

        return ($result->partner_registration->status == 'DOMAIN REGISTERED');
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $params = 'domainname=' . $domain->getName();
        $params .= '&monthsofregistration=12';
        
        $result = $this->_request('renew', $params);

        return ($result->partner_renew->status == 'DOMAIN RENEWED');
    }

    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
    	throw new Registrar_Exception("dotTK does not support Privacy protection");
    }

    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
    	throw new Registrar_Exception("dotTK does not support Privacy protection");
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
	private function _request($command, $params)
    {
        // Set authentication params
        $params .= '&email=' . $this->config['email'];
        $params .= '&password=' . $this->config['password'];
        
		$ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->_getApiUrl() . $command . '?' . $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            
		$result = curl_exec($ch);

        if ($result === false) {
            $e = new Registrar_Exception(sprintf('CurlException: "%s"', curl_error($ch)));
            $this->getLog()->err($e);
            curl_close($ch);
            throw $e;
        }
		curl_close($ch);
        
        $xml = new SimpleXMLElement($result);
        if ($xml->status == 'NOT OK')
        {
            throw new Registrar_Exception($xml->reason);
        }

        return $xml;
    }

    /**
     * Api URL.
     * @return string
     */
    private function _getApiUrl()
    {
        return 'https://api.domainshare.tk/';
    }
}