<?php

class Box_Mod_Importwhmcs_Controller_Admin
{
    public function fetchNavigation()
    {
        return array(
            'subpages'=>array(
                array(
                    'location'  => 'extensions',
                    'index'     => 1900,
                    'label'     => 'Whmcs Importer',
                    'uri'       => '/importwhmcs',
                    'class'     => '',
                ),
            ),
        );
    }
    
    public function register(Box_App &$app)
    {
        $app->get('/importwhmcs', 'get_index', array(), get_class($this));
    }

    public function get_index(Box_App $app)
    {
        $api = $app->getApiAdmin();
        return $app->render('mod_importwhmcs_index');
    }
}