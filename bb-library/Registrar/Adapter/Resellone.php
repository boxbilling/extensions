<?php

class Registrar_Adapter_Resellone extends Registrar_Adapter_Opensrs
{
    public function __construct($options)
    {
        if (!extension_loaded('curl')) {
            throw new Registrar_Exception('CURL extension is not enabled');
        }

        if(isset($options['user']) && !empty($options['user'])) {
            $this->config['user'] = $options['user'];
            unset($options['user']);
        } else {
            throw new Registrar_Exception('Domain registrar "ResellOne" is not configured properly. Please update configuration parameter "ReselleOne Username" at "Configuration -> Domain registration".');
        }

        if(isset($options['key']) && !empty($options['key'])) {
            $this->config['key'] = $options['key'];
            unset($options['key']);
        } else {
            throw new Registrar_Exception('Domain registrar "ResellOne" is not configured properly. Please update configuration parameter "ReselleOne key" at "Configuration -> Domain registration".');
        }

        if(isset($options['client_user']) && !empty($options['client_user'])) {
            $this->config['client_user'] = $options['client_user'];
            unset($options['client_user']);
        } else {
            throw new Registrar_Exception('Domain registrar "ResellOne" is not configured properly. Please update configuration parameter "ResellOne client username" at "Configuration -> Domain registration".');
        }

        if(isset($options['client_pass']) && !empty($options['client_pass'])) {
            $this->config['client_pass'] = $options['client_pass'];
            unset($options['client_pass']);
        } else {
            throw new Registrar_Exception('Domain registrar "ResellOne" is not configured properly. Please update configuration parameter "ResellOne client password" at "Configuration -> Domain registration".');
        }
    }

    public static function getConfig()
    {
        return array(
            'label'     =>  'Manages domains on ReselleOne via API',
            'form'  => array(
                'user' => array('text', array(
                            'label' => 'ResellOne Username',
                            'description'=>'ResellOne Username'
                        ),
                     ),
                'key' => array('password', array(
                            'label' => 'ResellOne key',
                            'description'=>'ResellOne key',
                            'renderPassword'    =>  true,
                        ),
                     ),
                'client_user' => array('text', array(
                            'label' => 'ResellOne client username',
                            'description'=>'The username of the registrant. For more information please visit www.resellone.net'
                        ),
                     ),
                'client_pass' => array('password', array(
                            'label' => 'ResellOne client password',
                            'description'=>'The registrants password. For more information please visit www.resellone.net',
                            'renderPassword'    =>  true,
                        ),
                     ),
            ),
        );
    }

    protected function _getApiUrl()
    {
        if ($this->isTestEnv())
            return 'horizon.opensrs.net';
        return 'resellers.resellone.net';
    }
}