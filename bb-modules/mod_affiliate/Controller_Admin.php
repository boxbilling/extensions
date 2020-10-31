<?php

class Box_Mod_Affiliate_Controller_Admin
{
    public function fetchNavigation()
    {
        return array(
            'subpages' => array(
                array(
                    'location'  => 'order',
                    'label' => 'Affiliates',
                    'uri' => 'affiliate',
                    'index'     => 1000,
                    'class'     => '',
                ),
            ),
        );
    }
    
    public function register(Box_App &$app)
    {
        $app->get('/affiliate', 'get_index', array(), get_class($this));
        $app->get('/affiliate/:id', 'get_aff', array('id' => '[0-9]+'), get_class($this));
    }

    public function get_index(Box_App $app)
    {
        $api = $app->getApiAdmin();
        return $app->render('mod_affiliate_index');
    }
    
    public function get_aff(Box_App $app, $id)
    {
        $api = $app->getApiAdmin();
        $aff = $api->affiliate_get(array('id'=>$id));
        return $app->render('mod_affiliate_profile', array('aff'=>$aff));
    }
}