<?php
class Registrar_Adapter_Dotid extends Registrar_AdapterAbstract
{
    public $config = array(
        'username'   => null,
        'token' => null,
    );

    public function __construct($options)
    {
        if (!extension_loaded('curl')) {
            throw new Registrar_Exception('CURL extension is not enabled');
        }

        if(isset($options['username']) && !empty($options['username'])) {
            $this->config['username'] = $options['username'];
            unset($options['username']);
        } else {
            throw new Registrar_Exception('Domain registrar "DotId" is not configured properly. Please update configuration parameter "DotId username" at "Configuration -> Domain registration".');
        }

        if(isset($options['token']) && !empty($options['token'])) {
            $this->config['token'] = $options['token'];
            unset($options['token']);
        } else {
            throw new Registrar_Exception('Domain registrar "DotId" is not configured properly. Please update configuration parameter "DotId token" at "Configuration -> Domain registration".');
        }
    }

    public static function getConfig()
    {
        return array(
            'label' => 'Manages domains on DotId via API',
            'form'  => array(
                'username' => array('text', array(
                            'label' => 'DotId username',
                            'description'=>'DotId username',
                    ),
                 ),
                'token' => array('password', array(
                            'label' => 'DotId token',
                            'description'=>'DotId token',
                            'renderPassword' => true,
                    ),
                 ),
            ),
        );
    }

    public function getTlds()
    {
    	throw new Registrar_Exception('Cannot register domain using DotId Api.');
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $curl_opts = array(
            CURLOPT_URL => 'whois.paneldotid.com',
            CURLOPT_PORT => 43,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_CUSTOMREQUEST => $domain->getName() . "\r\n",
        );
        
        $ch = curl_init();
            curl_setopt_array($ch, $curl_opts);
        $result = curl_exec($ch);

        return (strpos($result, 'NOT FOUND') !== false);
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        $dom = file_get_contents($this->_getApiUrl() . "getdomain.php?uid={$this->config['username']}&token={$this->config['token']}&domain={$domain->getName()}");
        $parse = explode("Error:", $dom);
        if (count($parse) > 1)
        {
            throw new Registrar_Exception($parse[1]); 
        }
        else 
        {       
            $ns_list = ''; $i = 1;
            foreach ($domain->getNameservers() as $ns)
                $ns_list .= '&ns' . $i++ . '=' . $ns->getHost();
            $getapi = file_get_contents($this->_getApiUrl() . "savens.php?session=12345&domain={$domain->getName()}" . $ns_list);
            if (strpos($getapi,"Anda Belum Login")) 
                echo "<script>window.open('{$this->_getApiUrl()}' + 'getsession.php?session=12345&domain={$domain->getName()}', 'Show Captcha','scrollbars=no,height=124,width=194')</script>";     
            $parse = explode("Error:", $getapi);
            if (count($parse) > 1) 
                throw new Registrar_Exception($parse[1]);
        }
        return true;
    }

    public function modifyContact(Registrar_Domain $domain)
    {
    	throw new Registrar_Exception('Cannot modify contact details using DotId Api.');
    }

    public function transferDomain(Registrar_Domain $domain)
    {
    	throw new Registrar_Exception('Cannot transfer domain using DotId Api.');
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
    	throw new Registrar_Exception('Cannot get domain details using DotId Api.');
    }

    public function deleteDomain(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('Registrar does not support domain removal.');
    }

    public function registerDomain(Registrar_Domain $domain)
    {  
    	throw new Registrar_Exception('Cannot register domain using DotId Api.');
    }

    public function renewDomain(Registrar_Domain $domain)
    {
    	throw new Registrar_Exception('Cannot renew domain using DotId Api.');
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
     * Api URL.
     * @return string
     */
    private function _getApiUrl()
    {
        return 'http://paneldotid.com/api/';
    }
}