<?php

class Box_Mod_Affiliate_Controller_Client
{
    public function register(Box_App &$app)
    {
        $app->get('/affiliate', 'get_index', array(), get_class($this));
        $app->get('/affiliate/stats', 'get_stats', array(), get_class($this));
        $app->get('/r/:id', 'get_ref', array('id' => '[0-9]+'), get_class($this));
    }

    public function get_index(Box_App $app)
    {
        return $app->render('mod_affiliate_index');
    }
    
    public function get_stats(Box_App $app)
    {
        $api = $app->getApiClient();
        if(!$api->affiliate_is_registered()) {
            throw new Box_Exception('You have to be affiliate to access this section.');
        }
        return $app->render('mod_affiliate_stats');
    }
    
    public function get_ref(Box_App $app, $id)
    {
        setcookie('bbr', $id, time() + 31556926, '/');
        
        $mod = new Box_Mod('affiliate');
        $config = $mod->getConfig();
        
        // track visit
        $service = $mod->getService();
        $service->track($id, Box_Tools::getIpv4());
        
        $url = (isset($config['redirect_to']) && !empty($config['redirect_to'])) ? $config['redirect_to'] : Box_Tools::url('/');
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: '.$url);
        exit;
    }
}