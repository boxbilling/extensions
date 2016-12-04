<?php
/**
 * Gandi domain registrar adapter for BoxBilling
 *
 * @copyright 7IN0's Labs (http://7in0.me)
 * @license   MIT
 */

class Registrar_Adapter_Gandi extends Registrar_AdapterAbstract
{
    #private $endpoint = 'https://rpc.gandi.net/xmlrpc/';
    private $endpoint = 'https://rpc.ote.gandi.net/xmlrpc/';

    public $config = array(
        'apikey' => null,
    );

    private function _call($method, $parameters = array())
    {
        array_unshift($parameters, $this->config['apikey']);
        $request = xmlrpc_encode_request($method, $parameters);

        $header[] = 'Content-type: text/xml';
        $header[] = 'Content-length: ' . strlen($request);
        $header[] = 'User-Agent: PHPRPC/1.0';

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $this->endpoint);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);

        $result = curl_exec($curl);

        if (curl_errno($curl)) {
            throw new Registrar_Exception('Domain registrar "Gandi" cannot access Gandi.net APIs.');
        } else {
            $result = xmlrpc_decode($result);
            if ($result['faultCode'] || $result['error']) {
                throw new Registrar_Exception('Domain registrar "Gandi" return an error.');
            } else {
                return $result;
            }
        }

        curl_close($curl);
    }

    public function _getContact(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();

        $contacts = $this->_call('contact.list');

        foreach ($contacts as $contact) {
            if ($contact['email'] == $c->getEmail() && $contact['phone'] == '+' . $c->getTelCc() . '.' . $c->getTel()) {
                $contact_gandi = $contact;
            }
        }

        if (!is_array($contact_gandi)) {
            $params = array(
                array(
                    'given' => $c->getFirstName(),
                    'family' => $c->getLastName(),
                    'email' => $c->getEmail(),
                    'streetaddr' => $c->getAddress1(),
                    'zip' => $c->getZip(),
                    'city' => $c->getCity(),
                    'country' => $c->getCountry(),
                    'phone' => '+' . $c->getTelCc() . '.' . $c->getTel(),
                    'type' => 0,
                    'password' => md5($c->getEmail() . time()),
                ),
            );

            $contact_gandi = $this->_call('contact.create', $params);
        }

        return $contact_gandi['handle'];
    }

    public function __construct($options)
    {
        if (!extension_loaded('curl')) {
            throw new Registrar_Exception('CURL extension is not enabled');
        }

        if (isset($options['apikey']) && !empty($options['apikey'])) {
            $this->config['apikey'] = $options['apikey'];
            unset($options['apikey']);
        } else {
            throw new Registrar_Exception('Domain registrar "Gandi" is not configured properly. Please update configuration parameter "Gandi API Key" at "Configuration -> Domain registration".');
        }
    }

    public function getTlds()
    {
        return array();
    }

    public static function getConfig()
    {
        return array(
            'label' => 'Manages domains on Gandi via API.',
            'form' => array(
                'apikey' => array('password', array(
                    'label' => 'Gandi API Key',
                    'description' => 'You can get this at Gandi control panel, go to Account management -> API management',
                    'required' => true,
                ),
                ),
            ),
        );
    }

    public function isDomainCanBeTransfered(Registrar_Domain $domain)
    {
        $params = array(
            $domain->getName(),
        );

        $result = $this->_call('domain.transferin.available', $params);

        if (is_bool($result)) {
            return $result;
        } else {
            return false;
        }
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $domainName = $domain->getName();
        $params = array(
            array($domainName),
        );

        $result = $this->_call('domain.available', $params);

        if (is_array($result) && !empty($result[$domainName]) && $result[$domainName] != 'unavailable') {
            return true;
        } else {
            return false;
        }
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        $params = array(
            $domain->getName(),
            array(
                $domain->getNs1(),
                $domain->getNs2(),
                $domain->getNs3(),
                $domain->getNs4(),
            ),
        );

        $result = $this->_call('domain.nameservers.set', $params);

        return true;
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        $contact = $this->_getContact($domain);

        $params = array(
            $contact,
            array(
                'domain' => $domain->getName(),
                'owner' => true,
                'admin' => true,
            ),
        );

        $result = $this->_call('contact.can_associate_domain', $params);

        if ($result) {
            $params = array(
                $domain->getName(),
                array(
                    'owner' => $contact,
                    'admin' => $contact,
                    'bill' => $contact,
                    'tech' => $contact,
                    'nameservers' => array(
                        $domain->getNs1(),
                        $domain->getNs2(),
                        $domain->getNs3(),
                        $domain->getNs4(),
                    ),
                    'authinfo' => $domain->getEpp(),
                    'duration' => 1,
                ),
            );

            $op = $this->_call('transferin.proceed', $params);

            if ($op['step'] != 'ERROR' || $op['step'] != 'BILLING_ERROR') {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
        $result = $this->_call('domain.info', array($domain->getName()));

        if (!$domain->getRegistrationTime()) {
            $domain->setRegistrationTime($result['date_created']->timestamp);
        }

        if (!$domain->getExpirationTime()) {
            $years = $domain->getRegistrationPeriod();
            $domain->setExpirationTime($result['date_registry_end']->timestamp);
        }

        return $domain;
    }

    public function deleteDomain(Registrar_Domain $domain)
    {
        /*
        $result = $this->_call('domain.delete.available', array($domain->getName()));

        if ($result) {
            $op = $this->_call('domain.delete.proceed', array($domain->getName()));

            if ($op['step'] != 'ERROR' || $op['step'] != 'BILLING_ERROR') {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
        */
        return true;
    }

    public function registerDomain(Registrar_Domain $domain)
    {
        $contact = $this->_getContact($domain);

        $params = array(
            $contact,
            array(
                'domain' => $domain->getName(),
                'owner' => true,
                'admin' => true,
            ),
        );

        $result = $this->_call('contact.can_associate_domain', $params);

        if ($result) {
            $params = array(
                $domain->getName(),
                array(
                    'owner' => $contact,
                    'admin' => $contact,
                    'bill' => $contact,
                    'tech' => $contact,
                    'nameservers' =>  array(
                        $domain->getNs1(),
                        $domain->getNs2(),
                        $domain->getNs3(),
                        $domain->getNs4(),
                    ),
                    'duration' => 1,
                ),
            );

            $op = $this->_call('domain.create', $params);

            if ($op['step'] != 'ERROR' || $op['step'] != 'BILLING_ERROR') {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $op = $this->_call('domain.renew', array(
            $domain->getName(),
            array(
                'duration' => $domain->getRegistrationPeriod(),
                'current_year' => $domain->getExpirationTime(),
            ),
        ));

        if ($op['step'] != 'ERROR' || $op['step'] != 'BILLING_ERROR') {
            return true;
        } else {
            return false;
        }
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        $contact = $this->_getContact($domain);

        $c = $domain->getContactRegistrar();

        $contact_gandi = $this->_call('contanct.update', array(
            $contact,
            array(
                'given' => $c->getFirstName(),
                'family' => $c->getLastName(),
                'email' => $c->getEmail(),
                'streetaddr' => $c->getAddress1(),
                'zip' => $c->getZip(),
                'city' => $c->getCity(),
                'country' => $c->getCountry(),
                'phone' => '+' . $c->getTelCc() . '.' . $c->getTel(),
                'type' => 0,
                'password' => md5($c->getEmail() . time()),
            ),
        ));

        if ($contact_gandi['handle'] == $contact) {
            return true;
        } else {
            return false;
        }
    }

    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $contact = $this->_getContact($domain);

        $contact_gandi = $this->_call('contanct.update', array(
            $contact,
            array(
                'data_obfuscated' => true,
                'mail_obfuscated' => true,
            ),
        ));

        if ($contact_gandi['handle'] == $contact) {
            return true;
        } else {
            return false;
        }
    }

    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $contact = $this->_getContact($domain);

        $contact_gandi = $this->_call('contanct.update', array(
            $contact,
            array(
                'data_obfuscated' => false,
                'mail_obfuscated' => false,
            ),
        ));

        if ($contact_gandi['handle'] == $contact) {
            return true;
        } else {
            return false;
        }
    }

    public function getEpp(Registrar_Domain $domain)
    {
        $result = $this->_call('domain.info', array($domain->getName()));

        return $result['authinfo'];
    }

    public function lock(Registrar_Domain $domain)
    {
        $this->_call('domain.status.lock', array($domain->getName()));

        return true;
    }

    public function unlock(Registrar_Domain $domain)
    {
        $this->_call('domain.status.unlock', array($domain->getName()));

        return true;
    }
}
