<?php

class Box_Mod_Phpminiadmin_Controller_Admin
{
    public function fetchNavigation()
    {
        return array(
            'subpages'=>array(
                array(
                    'location'  => 'system',
                    'index'     => 1800,
                    'label' => 'PHP Mini MySQL Admin',
                    'uri'   => '/phpminiadmin',
                    'class' => '',
                ),
            ),
        );
    }
    
    public function register(Box_App &$app)
    {
        $app->get('/phpminiadmin', 'get_index', array(), get_class($this));
    }

    public function get_index(Box_App $app)
    {
        $api = $app->getApiAdmin();
        return $app->render('mod_phpminiadmin_index');
    }
}