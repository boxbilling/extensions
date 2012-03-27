<?php
/**
 * BoxBilling
 *
 * LICENSE
 *
 * This source file is subject to the license that is bundled
 * with this package in the file LICENSE.txt
 * It is also available through the world-wide-web at this URL:
 * http://www.boxbilling.com/LICENSE.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@boxbilling.com so we can send you a copy immediately.
 *
 * @copyright Copyright (c) 2010-2012 BoxBilling (http://www.boxbilling.com)
 * @license   http://www.boxbilling.com/LICENSE.txt
 * @version   $Id$
 */
class Registrar_Adapter_Domainbox extends Registrar_AdapterAbstract
{
    public $config = array(
        'reseller' => null,
        'user'   => null,
        'password' => null
    );

    public function __construct($options)
    {
        if(!extension_loaded('soap')) {
            throw new Registrar_Exception('Soap extension required for DomainBox registrar');
        }
        
        if(isset($options['reseller']) && !empty($options['reseller'])) {
            $this->config['reseller'] = $options['reseller'];
            unset($options['reseller']);
        } else {
            throw new Registrar_Exception('Domain registrar "Domainbox" is not configured properly. Please update configuration parameter "Domainbox Reseller" at "Configuration -> Domain registration".');
        }
        
        if(isset($options['user']) && !empty($options['user'])) {
            $this->config['user'] = $options['user'];
            unset($options['user']);
        } else {
            throw new Registrar_Exception('Domain registrar "Domainbox" is not configured properly. Please update configuration parameter "Domainbox Username" at "Configuration -> Domain registration".');
        }

        if(isset($options['password']) && !empty($options['password'])) {
            $this->config['password'] = $options['password'];
            unset($options['password']);
        } else {
            throw new Registrar_Exception('Domain registrar "Domainbox" is not configured properly. Please update configuration parameter "Domainbox Password" at "Configuration -> Domain registration".');
        }
    }

    public static function getConfig()
    {
        return array(
            'label' => 'Manages domains on Domainbox via API',
            'form'  => array(
                'reseller' => array('text', array(
                            'label' => 'Domainbox Reseller',
                            'description'=>'Domainbox Reseller',
                    ),
                 ),
                'user' => array('text', array(
                            'label' => 'Domainbox Username',
                            'description'=>'Domainbox Username',
                    ),
                 ),
                'password' => array('password', array(
                            'label' => 'Domainbox Password',
                            'description'=>'Domainbox Password',
                            'renderPassword' => true,
                    ),
                 ),
            ),
        );
    }

    public function getTlds()
    {
        return array(
            'asia', 'biz', 'com', 'info', 'mobi', 'net', 'org', 
            'tel', 'xxx', 'eu', 'me', 'tv', 'cc', 'im', 'us', 
            'in', 'co', 'cx', 'gs', 'ht', 'ki', 'mu', 'nf', 'tl',
            'at', 'be', 'so', 'la', 
            'br.com', 'gb.net', 'uk.com', 'uk.net', 'uy.com', 
            'hu.com', 'no.com', 'ru.com', 'sa.com', 'se.com', 
            'se.net', 'za.com', 'jpn.com', 'eu.com', 'gb.com', 
            'us.com', 'qc.com', 'de.com', 'ae.org', 'kr.com', 
            'ar.com', 'cn.com', 'gr.com', 'com.cc', 
            'net.cc', 'org.cc', 'art.ht', 'org.ht', 'com.ht', 
            'net.ht', 'pro.ht', 'firm.ht', 'info.ht', 'shop.ht', 
            'adult.ht', 'pol.ht', 'rel.ht', 'asso.ht', 'perso.ht', 
            'biz.ki', 'com.ki', 'net.ki', 'org.ki', 'tel.ki', 
            'info.ki', 'mobi.ki', 'phone.ki', 'ac.mu', 'co.mu', 
            'net.mu', 'com.mu', 'org.mu', 'com.nf', 'net.nf', 
            'per.nf', 'web.nf', 'arts.nf', 'firm.nf', 'info.nf', 
            'store.nf', 'rec.nf', 'other.nf', 'com.sb', 'net.sb', 
            'org.sb', 'tl',
        );
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $params = array(
            'DomainName' => $domain->getName(),
            'LaunchPhase' => 'GA',
        );
        
        $result = $this->_request('CheckDomainAvailability', $params);
        
        return ($result->AvailabilityStatus == 0)
                && ($result->AvailabilityStatusDescr == 'Available');
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

    public function modifyNs(Registrar_Domain $domain)
    {
        $params = array(
            'DomainName' => $domain->getName(),
        );
        
        $params['Nameservers']['NS1'] = $domain->getNs1();
        $params['Nameservers']['NS2'] = $domain->getNs2();
        if($domain->getNs3())  {
            $params['Nameservers']['NS3'] = $domain->getNs3();
        }
        if($domain->getNs4())  {
            $params['Nameservers']['NS4'] = $domain->getNs4();
        }

        $result = $this->_request('ModifyDomainNameservers', $params);
        return true;
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();
        
        $contact = array(
            'Name' => $c->getName(),
            'Street1' => $c->getAddress1(),
            'Street2' => $c->getAddress2(),
            'Street3' => $c->getAddress3(),
            'City' => $c->getCity(),
            'State' => $c->getState(),
            'Postcode' => $c->getZip(),
            'CountryCode' => $c->getCountry(),
            'Telephone' => '+' . $c->getTelCc() . '.' . $c->getTel(),
            'Email' => $c->getEmail(),
            // .us additional parameters
            'Nexus' => array(
                'AppPurpose' => 'P3',
                'Category' => 'C11',
            ),
        );
        
        // Change the Name of every contact to register successfully
        $admin = $tech = $bill = $contact;
        $admin['Name'] .= 'a';
        $tech['Name'] .= 't';
        $bill['Name'] .= 'b';
        
        $params = array(
            'DomainName' => $domain->getName(),
            'Contacts' => array(
                'Registrant' => $contact,
                'Admin' => $admin,
                'Tech' => $tech,
                'Billing' => $bill,
            ),
        );
        $result = $this->_request('ModifyDomainContacts', $params);
        
        return true;
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();
        
        $contact = array(
            'Name' => $c->getName(),
            'Street1' => $c->getAddress1(),
            'Street2' => $c->getAddress2(),
            'Street3' => $c->getAddress3(),
            'City' => $c->getCity(),
            'State' => $c->getState(),
            'Postcode' => $c->getZip(),
            'CountryCode' => $c->getCountry(),
            'Telephone' => '+' . $c->getTelCc() . '.' . $c->getTel(),
            'Email' => $c->getEmail(),
            // .us additional parameters
            'Nexus' => array(
                'AppPurpose' => 'P3',
                'Category' => 'C11',
            ),
        );
        
        $admin = $tech = $bill = $contact;
        $admin['Name'] .= 'a';
        $tech['Name'] .= 't';
        $bill['Name'] .= 'b';
        
        $params = array(
            'DomainName' => $domain->getName(),
            'AutoRenew' => false,
            'AutoRenewDays' => 1,
            'AcceptTerms' => true,
            'KeepExistingNameservers' => true,
            'Contacts' => array(
                'Registrant' => $contact,
                'Admin' => $admin,
                'Tech' => $tech,
                'Billing' => $bill,
            ),
        );
        $this->_request('RequestTransfer', $params);
        
        return true;
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
        $params = array(
            'DomainName' => $domain->getName(),
        );
        $result = $this->_request('QueryDomain', $params);
        
        $contact = $result->Contacts->Registrant;
        
        $tel = explode(".", $contact->Telephone);
        $tel[0] = str_replace('+', '', $tel[0]);
        
        $c = new Registrar_Domain_Contact();
        $c->setName($contact->Name)
          ->setEmail($contact->Email)
          ->setTel($tel[1])
          ->setTelCc($tel[0])
          ->setAddress1($contact->Street1)
          ->setAddress2($contact->Street2)
          ->setCity($contact->City)
          ->setCountry($contact->CountryCode)
          ->setZip($contact->Postcode);

        $params = array(
            'DomainName' => $domain->getName(),
        );
        $epp = $this->_request('QueryDomainAuthcode', $params);
        
        $domain->setNameservers($ns_list);
        $domain->setExpirationTime(strtotime($result->ExpiryDate));
        $domain->setRegistrationTime(strtotime($result->CreatedDate));
        $domain->setEpp($epp->AuthCode);
        $domain->setPrivacyEnabled($result->ApplyPrivacy);
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
        
        $ns_list['NS1'] = $domain->getNs1();
        $ns_list['NS2'] = $domain->getNs2();
        if($domain->getNs3())  {
            $ns_list['NS3'] = $domain->getNs3();
        }
        if($domain->getNs4())  {
            $ns_list['NS4'] = $domain->getNs4();
        }
        
        $contact = array(
            'Name' => $c->getName(),
            'Street1' => $c->getAddress1(),
            'Street2' => $c->getAddress2(),
            'Street3' => $c->getAddress3(),
            'City' => $c->getCity(),
            'State' => $c->getState(),
            'Postcode' => $c->getZip(),
            'CountryCode' => $c->getCountry(),
            'Telephone' => '+' . $c->getTelCc() . '.' . $c->getTel(),
            'Email' => $c->getEmail(),
            // .us additional parameters
            'Nexus' => array(
                'AppPurpose' => 'P3',
                'Category' => 'C11',
            ),
        );
        
        // Change the Name of every contact to register successfully
        $admin = $tech = $bill = $contact;
        $admin['Name'] .= 'a';
        $tech['Name'] .= 't';
        $bill['Name'] .= 'b';
        
        $params = array(
            'DomainName' => $domain->getName(),
            'LaunchPhase' => 'GA',
            'ApplyLock' => false,
            'AutoRenew' => false,
            'AutoRenewDays' => 1,
            'ApplyPrivacy' => false,
            'AcceptTerms' => true,
            'Period' => $domain->getRegistrationPeriod(),
            'Nameservers' => $ns_list,
            'Contacts' => array(
                'Registrant' => $contact,
                'Admin' => $admin,
                'Tech' => $tech,
                'Billing' => $bill,
            ),
        );
        
        if (($domain->getTld() == 'eu') || ($domain->getTld() == 'be')) {
            unset($params['Contacts']['Admin']);
            unset($params['Contacts']['Billing']);
        }
        $this->_request('RegisterDomain', $params);
        
        return true;
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $params = array(
            'DomainName' => $domain->getName(),
            'Period' => $domain->getRegistrationPeriod(),
            'CurrentExpiry' => date('Y-m-d', $domain->getExpirationTime()),
        );
        $this->_request('RenewDomain', $params);
        
        return true;
    }

    public function togglePrivacyProtection(Registrar_Domain $domain)
    {
        $params = array(
            'DomainName' => $domain->getName(),
            'ApplyPrivacy' => !$domain->getPrivacyEnabled(),
        );
        $result = $this->_request('ModifyDomainPrivacy', $params);
        
        return true;
    }

	/**
   	 * Runs an api command and returns parsed data.
 	 * @param string $cmd
 	 * @param array $params
 	 * @return array
 	 */
	private function _request($cmd, $params)
    {
        $params = array(
            'CommandParameters' => $params,
            'AuthenticationParameters' => array(
                'Reseller' => $this->config['reseller'],
                'Username' => $this->config['user'],
                'Password' => $this->config['password'],
            ),
        );
        
        if ($this->_testMode) {
            $wsdl = 'https://sandbox.domainbox.net/?wsdl';
        } else {
            $wsdl = 'https://live.domainbox.net/?wsdl';
        }
        
        $client	= new SoapClient($wsdl,
            array('soap_version' => SOAP_1_2)
        );
        
        $result = $client->$cmd($params);
        
        $this->getLog()->debug(print_r($params, true));
        $this->getLog()->debug(print_r($result, true));
        
        if ($result->{$cmd . 'Result'}->ResultCode != 100) {
            throw new Registrar_Exception($result->{$cmd . 'Result'}->ResultMsg);
        }
        
        return $result->{$cmd . 'Result'};
	}
}