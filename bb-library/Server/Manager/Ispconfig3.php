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
class Server_Manager_Ispconfig3 extends Server_Manager
{
    private $_session = null;
    private $_c = null;

	public function init()
    {
        if(!extension_loaded('soap')) {
            throw new Server_Exception('PHP Soap extension required for IspConfig server manager');
        }

        if (!extension_loaded('curl')) {
            throw new Server_Exception('PHP cURL extension is not enabled');
        }
	}

    public function  __destruct()
    {
        if($this->_c instanceof SoapClient && $this->_session) {
            $this->_request('logout');
            unset($this->_c, $this->_session);
        }
    }

    public static function getForm()
    {
        return array(
            'label'     =>  'ISPConfig 3',
        );
    }

    public function getLoginUrl()
    {
        $host     = $this->_config['host'];
        return 'http://'.$host.':8080';
    }

    public function getResellerLoginUrl()
    {
        return $this->getLoginUrl();
    }
    
    public function testConnection()
    {
        $this->_load();
        return true;
    }
    
    public function synchronizeAccount(Server_Account $a)
    {
        $this->getLog()->info('Synchronizing account with server '.$a->getUsername());
        return $a;
    }

    public function createAccount(Server_Account $a)
    {
        $ci = $this->getClient($a);
		try {
        	if (!$ci)
        	{
	            if ($a->getReseller())
    	            $id = $this->createClient($a, 1);
        	    else
            	    $id = $this->createClient($a, 0);
        	}
	        else
    	    {
        	    $id = $ci['client_id'];
        	}

        	$client = $a->getClient();
        	$client->setAId($id);

	        $this->createSite($a);
        	$this->dnsCreateZone($a);
		} catch (Exception $e) {
			if (strpos(strtolower($e->getMessage()), strtolower('domain_error_unique')) === false) {
				throw new Server_Exception($e->getMessage());
			} else {
				return true;
			}
		}
        return true;
    }

    public function suspendAccount(Server_Account $a)
    {
        $params = array(
            'primary_id' => $this->getSiteId($a)
        );

        $result = $this->_request('sites_web_domain_inactive', $params);

        return (bool) $result;
    }

    public function unsuspendAccount(Server_Account $a)
    {
        $params = array(
            'primary_id' => $this->getSiteId($a)
        );

        $result = $this->_request('sites_web_domain_active', $params);

        return (bool) $result;
    }

    public function cancelAccount(Server_Account $a)
    {
        $ci = $this->getClient($a);

        $params = array(
            'client_id' => $ci['client_id']
        );

        $result = $this->_request('client_delete', $params);

        return (bool) $result;
    }

    public function changeAccountPackage(Server_Account $a, Server_Package $p)
    {
        $client     = $a->getClient();

        $ci = $this->getClient($a);

        $params = array(
            'reseller_id' => 1,
            'client_id' => $ci['client_id'],

            'server_id'     => $this->getServerId(),
            'company_name'  => $client->getCompany(),
            'contact_name'  => $client->getFullName(),
            'username'      => $a->getUsername(),
            'password'      => $a->getPassword(),
            
            'language'      => $p->getCustomValue('languge'),
            'usertheme'     => $p->getCustomValue('theme'),
            
            'street'        => $client->getStreet(),
            'zip'           => $client->getZip(),
            'city'          => $client->getCity(),
            'state'         => $client->getState(),
            'country'       => $client->getCountry(),
            'telephone'     => $client->getTelephone(),
            'mobile'        => $client->getTelephone(),
            'fax'           => $client->getTelephone(),
            'email'         => $a->getEmail(),
            'internet'      => $a->getWww(),
            'icq'           => '',
            'notes'         => $a->getNote(),
        );

        $result = $this->_request('client_update', $params);

        return (bool) $result;
    }

    public function changeAccountPassword(Server_Account $a, $new)
    {
        $ci = $this->getClient($a);

        $params = array(
            'client_id' => $ci['client_id'],
            'password' => $new
        );

        $result = $this->_request('client_change_password', $params);

        return (bool) $result;
    }

    public function changeAccountUsername(Server_Account $a, $new)
    {
        throw new Server_Exception('Server manager does not support username changes');
    }
    
    public function changeAccountDomain(Server_Account $a, $new)
    {
        throw new Server_Exception('Server manager does not support domain changes');
    }

    public function changeAccountIp(Server_Account $a, $new)
    {
        throw new Server_Exception('Server manager does not support ip changes');
    }
    
    private function createSite(Server_Account &$a)
    {
        if($this->isSiteCreated($a)) {
            return true;
        }

        $client     = $a->getClient();
        $package    = $a->getPackage();
        $server     = $this->getServerInfo();

        $site_params['client_id']       = $a->getAId();
        $site_params['domain']          = $a->getDomain();
        $site_params['type'] 			= 'vhost';	// harcoded in ISPConfig vhost
        $site_params['vhost_type'] 		= 'name';	// harcoded in ISPConfig vhost

        $site_params['system_user'] 	= 1;//1; force to the admin
        $site_params['system_group'] 	= 1; //as added by the admin

        $site_params['client_group_id'] = $client->getAid() + 1;	 //always will be this 	groupd id + 1
        $site_params['server_id'] 		= $this->getServerId();
        
        $site_params['added_by'] 		= $client->getId();
        
        $site_params['backup_interval'] 		= "weekly";
        $site_params['backup_copies'] 		= '2';

        //Set the defaults
        $site_params['hd_quota'] 		= $package->getQuota();
        $site_params['traffic_quota'] 	= $package->getBandwidth();
        
        $site_params['subdomain']       = 'www';

        //Hardcoded values
        $site_params['allow_override'] 	= 'All';
        $site_params['errordocs'] 		= 1;

        $site_params['document_root'] 	 = $server['website_path'];
        $site_params['php_open_basedir'] = $server['php_open_basedir'];

        //PHP Configuration
        $site_params['php'] 			= 'suphp'; //php available posible values
        $site_params['ip_address'] 		= '*'; //important
        $site_params['active']          = 'y';

        //Creating a site
        $result = $this->_request('sites_web_domain_add', $site_params);
        return $result;
    }

    private function dnsCreateZone(Server_Account &$a)
    {
        $client     = $a->getClient();

        //Adding the DNS record A
        $dns_a_params['server_id'] = $this->getServerId();
        $dns_a_params['client_id'] = $client->getAid();
        $dns_a_params['zone'] = '90';
        $dns_a_params['name'] = $a->getDomain().'.'; //adding a final dot
        $dns_a_params['type'] = 'A';
        $dns_a_params['data'] = $a->getIp();
        $dns_a_params['ttl'] = '86400';
        $dns_a_params['active'] = 'Y';

        $this->_request('dns_a_add', $dns_a_params);
        /*
        // ---- Setting up the mail domain
        $mail_domain_params['client_id'] 	= $client->getAId();
        $mail_domain_params['server_id']  	= $this->getServerId();
        $mail_domain_params['domain']	 	= $a->getDomain();
        $mail_domain_params['active'] 	 	= 'y';

        $domain_id = $this->_request('mail_domain_add', $mail_domain_params);

        // ---- Setting up the DNS ZONE
        $dns_domain_params['client_id'] = $client->getAId();
        $dns_domain_params['server_id'] = $this->getServerId();
        $dns_domain_params['origin']	= $a->getDomain();

        $dns_domain_params['ns']		= '8.8.8.8';
        $dns_domain_params['mbox'] 		= 'mbox.beeznest.com.';//@todo
        $dns_domain_params['refresh'] 	= 28800;
        $dns_domain_params['retry'] 	= 7200;
        $dns_domain_params['expire']	= 604800;
        $dns_domain_params['minimum']	= 604800;
        $dns_domain_params['ttl']		= 604800;
        $dns_domain_params['active'] 	= 'y';
        $result = $this->remote('dns_zone_add', $dns_domain_params);
        */

        return true;
    }

    private function createClient(Server_Account &$a, $type)
    {
        $client     = $a->getClient();
        $p          = $a->getPackage();
        $params = array(
            'server_id' => $this->getServerId(),
            'company_name' => $client->getCompany(),
            'contact_name' => $client->getFullName(),
            'username' =>$a->getUsername(),
            'password' =>$a->getPassword(),
            'language'      => $p->getCustomValue('languge'),
            'usertheme'     => $p->getCustomValue('theme'),
            'street' =>$client->getStreet(),
            'zip' =>$client->getZip(),
            'city' =>$client->getCity(),
            'state' =>$client->getState(),
            'country' =>$client->getCountry(),
            'telephone' =>$client->getTelephone(),
            'mobile' =>$client->getTelephone(),
            'fax' =>$client->getTelephone(),
            'email' =>$a->getEmail(),
            'internet' =>$a->getWww(),
            'icq' =>'',
            'notes' =>$a->getNote(),

            'template_master' => '0',
            'template_additional' =>'',

            'default_mailserver' =>'1',
            'limit_maildomain' =>'1',
            'limit_mailbox' =>'-1',
            'limit_mailalias' =>'-1',
            'limit_mailforward' =>'-1',
            'limit_mailcatchall' =>'-1',
            'limit_mailrouting' => '-1',
            'limit_mailfilter' =>'-1',
            'limit_fetchmail' =>'-1',
            'limit_mailquota' =>'-1',
            'limit_spamfilter_wblist' =>'-1',
            'limit_spamfilter_user' =>'-1',
            'limit_spamfilter_policy' =>'-1',

            'default_webserver' =>'1',
            'limit_web_domain' =>'-1',
            'web_php_options' =>"SuPHP",
            'limit_web_aliasdomain' =>'-1',
            'limit_web_subdomain' =>'-1',
            'limit_ftp_user' =>'-1',
            'limit_shell_user' =>'-1',
            'ssh_chroot' =>'None',

            'default_dnsserver' =>'1',
            'limit_dns_zone' =>'-1',
            'limit_dns_record' =>'-1',
            'limit_client' => $type,

            'default_dbserver' =>'1',
            'limit_database' =>'-1',
            'limit_cron' =>'0',
            'limit_cron_type' =>'',
            'limit_cron_frequency' =>'-1',
        );
        $action = 'client_add';
        $result = $this->_request($action, $params);

        return $result;
    }

    private function getClient(Server_Account $a)
    {
		$params['username'] = $a->getUsername();
        $result = $this->_request('client_get_by_username',$params);
        return $result;
    }

    private function isSiteCreated(Server_Account $a)
    {
        $sites = $this->getClientSites($a);
        if (is_array($sites) ) {
            foreach($sites as $key=>$domain) {
                if ($a->getDomain() == $domain['domain']) {
                    $my_domain = $domain;
                    return true;
                }
            }
        }
        return false;
    }

    private function getClientSites(Server_Account $a)
    {
        $user_info = $this->getClient($a);
        $site_params['sys_userid']	= $user_info['userid'];
        $site_params['groups'] 		= $user_info['groups'];

        $site_info = $this->_request('client_get_sites_by_user', $site_params);
        return $site_info;
    }


    private function getSiteId(Server_Account $a)
    {
        $sites = $this->getClientSites($a);
        if (is_array($sites) ) {
            foreach($sites as $key=>$domain) {
                if ($a->getDomain() == $domain['domain']) {
                    return $domain['domain_id'];
                }
            }
        }
        return false;
    }

    private function getSiteInfo(Server_Account $a)
    {
        $server_params['server_id'] 	= $this->getServerId();
        $server_params['section'] 		= $section;
        return $this->_request('server_get',$server_params);
    }

    private function getServerInfo($section = 'web')
    {
        $server_params['server_id'] 	= $this->getServerId();
        $server_params['section'] 		= $section;
        return $this->_request('server_get',$server_params);
    }

    private function getServerId()
    {
        return 1;
//        return $this->_config['server_id'];
    }

    private function _load()
    {
        $usessl   = $this->_config['secure'];
        $host     = $this->_config['host'];
        $username = $this->_config['username'];
        $password = $this->_config['password'];
        $host = ($usessl) ? 'https://'.$host : 'http://'.$host;

        $soap_location = $host.':8080/remote/index.php';
        $soap_uri = $host.':8080/remote/';

        if(!$this->_c instanceof SoapClient ) {
            // Create the SOAP Client
            $this->_c = new SoapClient(null, array('location' => $soap_location,
                                                 'uri'      => $soap_uri));
        }

        //* Login to the remote server
        if($this->_session === null) {
            try {
                $this->_session = $this->_c->login($username, $password);
            } catch(Exception $e) {
                throw new Server_Exception($e->getMessage(), $e->getCode());
            }
        }

        if(!$this->_c instanceof SoapClient) {
            throw new Server_Exception('Could not load Soap client');
        }
        if(!$this->_session) {
            throw new Server_Exception('Could not retrieve session');
        }

        return $this;
    }

    private function _request($action, $params = array())
    {
		$this->getLog()->debug(sprintf('ISP Config 3 action "%s" called with params: "%s" ', $action, print_r($params,1)));

		$this->_load();
        $soap_client = $this->_c;

        try {
            switch($action) {
                case 'client_add':
                    $reseller_id = 1;
                    $soap_result	= $soap_client->client_add($this->_session, $reseller_id, $params);
                break;
                case 'client_get':
                    $soap_result 	= $soap_client->client_get($this->_session, $params['client_id']);
                break;
                case 'client_get_by_username':
                    $soap_result 	= $soap_client->client_get_by_username($this->_session, $params['username']);
                break;
                case 'client_get_sites_by_user':
                    $soap_result 	= $soap_client->client_get_sites_by_user($this->_session, $params['sys_userid'], $params['groups']);
                break;
                case 'client_delete':
                    $soap_result 	= $soap_client->client_delete($this->_session, $params['client_id']);
                break;
                case 'client_update':
                    $soap_result 	= $soap_client->client_update($this->_session, $params['client_id'], $params['reseller_id'], $params);
                break;
                case 'client_change_password':
                    $soap_result 	= $soap_client->client_change_password($this->_session, $params['client_id'], $params['password']);
                break;
                case 'sites_cron_add':
                    //$soap_result = $soap_client->sites_cron_add($this->_session, $reseller_id, $site);
                break;
                case 'sites_web_domain_update':
                    $client_id 		= $params['client_id']; // client id
                    $primary_id		= $params['primary_id']; //site id
                    $params['client_id'] = $params['primary_id'] = null;
                    $soap_result 	= $soap_client->sites_web_domain_update($this->_session, $client_id, $primary_id, $params);
                break;
                case 'sites_web_domain_active':
                    $primary_id		= $params['primary_id']; //site id
                    $soap_result 	= $soap_client->sites_web_domain_set_status($this->_session, $primary_id, 'active');
                break;
                case 'sites_web_domain_inactive':
                    $primary_id		= $params['primary_id']; //site id
                    $soap_result 	= $soap_client->sites_web_domain_set_status($this->_session, $primary_id,'inactive');
                break;
                case 'sites_web_domain_add':
                    $client_id = $params['client_id'];
                    $params['client_id'] = null;
                    $soap_result 	= $soap_client->sites_web_domain_add($this->_session, $client_id  , $params);
                break;
                case 'sites_web_domain_update':
                    $client_id = $params['client_id'];
                    $params['client_id'] = null;
                    $soap_result 	= $soap_client->sites_web_domain_update($this->_session, $client_id  , $params);
                break;
                case 'sites_web_subdomain_add':
                    $client_id = $params['client_id'];
                    $params['client_id'] = null;
                    $soap_result 	= $soap_client->sites_web_subdomain_add($this->_session, $client_id  , $params);
                break;
                //Get domain info
                case 'sites_web_domain_get':
                    $soap_result 	= $soap_client->sites_web_domain_get($this->_session, $params['primary_id']);
                break;
                //Get server info
                case 'server_get':
                    $soap_result 	= $soap_client->server_get($this->_session, $params['server_id'], $params['section']);//Section Could be 'web', 'dns', 'mail', 'dns', 'cron', etc
                break;
                //Adds a DNS zone
                case 'dns_zone_add':
                    $client_id 		= $params['client_id']; // client id
                    $params['client_id'] = null;
                    $soap_result 	= $soap_client->dns_zone_add($this->_session, $client_id, $params);
                break;
                case 'dns_zone_get':
                    $soap_result 	= $soap_client->dns_zone_get($this->_session, $client_id, $params);
                break;
                case 'dns_zone_get_by_user':
                    $client_id 		= $params['client_id']; // client id
                    $soap_result 	= $soap_client->dns_zone_get_by_user($this->_session, $client_id, $params);
                break;
                case 'dns_zone_update':
                    /*$client_id 		= $params['client_id']; // client id
                    $primary_id		= $params['primary_id']; // client id
                    $params['client_id'] = null;
                    $params['primary_id'] = null;
                    $soap_result 	= $soap_client->dns_zone_update($this->_session, $client_id, $primary_id, $params);*/
                break;
                case 'dns_zone_inactive':
                    $primary_id		= $params['primary_id']; // client id
                    $soap_result 	= $soap_client->dns_zone_set_status($this->_session, $primary_id, 'inactive');
                break;
                case 'dns_zone_active':
                    $primary_id		= $params['primary_id']; // client id
                    $soap_result 	= $soap_client->dns_zone_set_status($this->_session, $primary_id, 'active');
                break;

                case 'dns_a_add':
                    $client_id		= $params['client_id']; // client id
                    $soap_result 	= $soap_client->dns_a_add($this->_session, $client_id, $params);
                break;

                case 'mail_domain_add':
                    $client_id 		= $params['client_id']; // client id
                    $params['client_id'] = null;
                    $soap_result 	= $soap_client->mail_domain_add($this->_session, $client_id, $params);
                break;
                //Add an email domain
                case 'mail_domain_update':
                    $client_id 		= $params['client_id']; // client id
                    $params['client_id'] = null;
                    $soap_result 	= $soap_client->mail_domain_update($this->_session, $client_id, $params);
                break;
                //Change domain status
                case 'mail_domain_active':
                    $primary_id 		= $params['primary_id'];
                    $soap_result 	= $soap_client->mail_domain_set_status($this->_session, $primary_id, 'active');
                break;
                //Change domain status
                case 'mail_domain_inactive':
                    $primary_id 		= $params['primary_id'];
                    $soap_result 	= $soap_client->mail_domain_set_status($this->_session, $primary_id, 'inactive');
                break;
                case 'mail_domain_get_by_domain':
                    $domain		= $params['domain'];
                    $soap_result 	= $soap_client->mail_domain_get_by_domain($this->_session, $domain);
                break;
                //Creates a mySQL database
                case 'sites_database_add':
                    $client_id 		= $params['client_id']; // client id
                    $params['client_id'] = null;
                    $soap_result 	= $soap_client->sites_database_add($this->_session, $client_id, $params);
                break;
                case 'sites_database_get':
                    $client_id 		= $params['client_id']; // client id
                    $params['client_id'] = null;
                    $soap_result 	= $soap_client->sites_database_get($this->_session, $client_id, $params);
                break;
                case 'sites_database_get_all_by_user':
                    $client_id 		= $params['client_id']; // client id
                    $params['client_id'] = null;
                    $soap_result 	= $soap_client->sites_database_get_all_by_user($this->_session, $client_id, $params);
                break;
                case 'install_chamilo':
                    $client_id 		= $params['client_id']; // client id
                    $params['client_id'] = null;
                    $soap_result 	= $soap_client->install_chamilo($this->_session, $client_id, $params);
                break;
                case 'client_templates_get_all':
                    $soap_result 	= $soap_client->client_templates_get_all($this->_session);
                break;
                case 'logout' :
                    $soap_result 	= $soap_client->logout($this->_session);
                break;

                default:

                break;
            }
        } catch (SoapFault $e) {
            throw new Server_Exception($e->getMessage(), $e->getCode(), $e);
        }

        return $soap_result;
    }
}
