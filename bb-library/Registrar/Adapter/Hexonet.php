<?php
/**
 * Hexonet domain registrar adapter for BoxBilling
 *
 * @copyright 7IN0's Labs (http://7in0.me)
 * @license   MIT
 */

class Registrar_Adapter_Hexonet extends Registrar_AdapterAbstract
{
    private $endpoint = 'https://xmlrpc-api.hexonet.net:8083';

    public $config = array(
        'username' => null,
        'password' => null,
    );

    private function _call($command, $parameters = array())
    {
        $parameters['s_login'] = $this->config['username'];
        $parameters['s_pw'] = $this->config['password'];
        $parameters['command'] = $command;

        $request = xmlrpc_encode_request('Api.xcall', $parameters);

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
            throw new Registrar_Exception('Domain registrar "Hexonet" cannot access Hexonet.net APIs.');
        } else {
            $result = xmlrpc_decode($result);
            return $result;
        }

        curl_close($curl);
    }

    public function _getContact(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();

        $params = array(
            'email' => $c->getEmail(),
        );

        $result = $this->_call('QueryContactList', $params);

        if ($result['CODE'] == 200) {
            if ($result['PROPERTY']['TOTAL'][0] >= 1) {
                return $result['PROPERTY']['CONTACT'][0];
            } else {
                $params = array(
                    'firstname' => $c->getFirstName(),
                    'lastname' => $c->getLastName(),
                    'street' => $c->getAddress1(),
                    'city' => $c->getCity(),
                    'zip' => $c->getZip(),
                    'country' => $c->getCountry(),
                    'phone' => '+' . $c->getTelCc() . '.' . $c->getTel(),
                    'email' => $c->getEmail(),
                );

                $result = $this->_call('AddContact', $params);

                if ($result['CODE'] == 200) {
                    return $result['PROPERTY']['CONTACT'][0];
                } else {
                    throw new Registrar_Exception('Domain registrar "Hexonet" cannot add contact info to Hexonet.net.');
                }
            }
        } else {
            throw new Registrar_Exception('Domain registrar "Hexonet" cannot query contact info from Hexonet.net APIs.');
        }
    }

    public function __construct($options)
    {
        if (!extension_loaded('curl')) {
            throw new Registrar_Exception('CURL extension is not enabled');
        }

        if (
            (isset($options['username']) && !empty($options['username'])) &&
            (isset($options['password']) && !empty($options['password']))
        ) {
            $this->config['username'] = $options['username'];
            $this->config['password'] = $options['password'];

            unset($options['username']);
            unset($options['password']);
        } else {
            throw new Registrar_Exception('Domain registrar "Hexonet" is not configured properly. Please update configuration parameter "Hexonet Username" and "Hexonet Password" at "Configuration -> Domain registration".');
        }
    }

    public function getTlds()
    {
        return array();
    }

    public static function getConfig()
    {
        return array(
            'label' => 'Manages domains on Hexonet via API.',
            'form' => array(
                'username' => array('text', array(
                    'label' => 'Hexonet Username',
                    'description' => 'Username of your Hexonet Reseller Account',
                    'required' => true,
                ),
                ),
                'password' => array('password', array(
                    'label' => 'Hexonet Password',
                    'description' => 'Password of your Hexonet Reseller Account',
                    'required' => true,
                ),
                ),
            ),
        );
    }

    public function isDomainCanBeTransfered(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
        );

        $result = $this->_call('CheckDomainTransfer', $params);

        if ($result['CODE'] == 218) {
            return true;
        } else {
            return false;
        }
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
        );

        $result = $this->_call('CheckDomain', $params);

        if ($result['CODE'] == 210) {
            return true;
        } else {
            return false;
        }
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
            'nameserver0' => $domain->getNs1(),
            'nameserver1' => $domain->getNs2(),
            'nameserver2' => $domain->getNs3(),
            'nameserver3' => $domain->getNs4(),
        );

        $result = $this->_call('ModifyDomain', $params);

        if ($result['CODE'] == 200) {
            return true;
        } else {
            return false;
        }
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
            'auth' => $domain->getEpp(),
            'action' => 'REQUEST',
        );

        $result = $this->_call('TransferDomain', $params);

        if ($result['CODE'] == 200) {
            return true;
        } else {
            return false;
        }
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
        );

        $result = $this->_call('StatusDomain', $params);

        if ($result['CODE'] == 200) {
            if (!$domain->getRegistrationTime()) {
                $domain->setRegistrationTime(strtotime($result['PROPERTY']['CREATEDDATE'][0]));
            }

            if (!$domain->getExpirationTime()) {
                $years = $domain->getRegistrationPeriod();
                $domain->setExpirationTime(strtotime($result['PROPERTY']['REGISTRATIONEXPIRATIONDATE'][0]));
            }
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
        return false;
    }

    public function registerDomain(Registrar_Domain $domain)
    {
        $contact = $this->_getContact($domain);

        $params = array(
            'domain' => $domain->getName(),
            'ownercontact1' => $contact,
            'admincontact1' => $contact,
            'techcontact1' => $contact,
            'billingcontact1' => $contact,
            'nameserver0' => $domain->getNs1(),
            'nameserver1' => $domain->getNs2(),
            'nameserver2' => $domain->getNs3(),
            'nameserver3' => $domain->getNs4(),
        );

        $result = $this->_call('AddDomain', $params);

        if ($result['CODE'] == 200) {
            return true;
        } else {
            return false;
        }
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
        );

        $result = $this->_call('RenewDomain', $params);

        if ($result['CODE'] == 200) {
            return true;
        } else {
            return false;
        }
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        $contact = $this->_getContact($domain);

        $params = array(
            'contact' => $contact,
            'firstname' => $c->getFirstName(),
            'lastname' => $c->getLastName(),
            'street' => $c->getAddress1(),
            'city' => $c->getCity(),
            'zip' => $c->getZip(),
            'country' => $c->getCountry(),
            'phone' => '+' . $c->getTelCc() . '.' . $c->getTel(),
            'email' => $c->getEmail(),
        );

        $result = $this->_call('ModifyContact', $params);

        if ($result['CODE'] == 200) {
            return true;
        } else {
            return false;
        }
    }

    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        return false;
    }

    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        return true;
    }

    public function getEpp(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
        );

        $result = $this->_call('StatusDomain', $params);

        if ($result['CODE'] == 200) {
            return $result['PROPERTY']['AUTH'][0];
        } else {
            return null;
        }
    }

    public function lock(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
            'addstatus0' => 'ClientTransferProhibited',
        );

        $result = $this->_call('ModifyDomain', $params);

        if ($result['CODE'] == 200) {
            return true;
        } else {
            return false;
        }
    }

    public function unlock(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
            'delstatus0' => 'ClientTransferProhibited',
        );

        $result = $this->_call('ModifyDomain', $params);

        if ($result['CODE'] == 200) {
            return true;
        } else {
            return false;
        }
    }
}
