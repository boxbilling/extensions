<?php

class Box_Mod_Massmailer_Controller_Admin
{
    public function fetchNavigation()
    {
        return array(
            'subpages'=>array(
                array(
                    'location'  => 'extensions',
                    'index'     => 4000,
                    'label' => 'Mass Mailer',
                    'uri'   => 'massmailer',
                    'class' => '',
                ),
            ),
        );
    }
    
    public function register(Box_App &$app)
    {
        $app->get('/massmailer', 'get_index', array(), get_class($this));
        $app->get('/massmailer/message/:id', 'get_edit', array('id'=>'[0-9]+'), get_class($this));
    }

    public function get_index(Box_App $app)
    {
        $api = $app->getApiAdmin();
        return $app->render('mod_massmailer_index');
    }
    
    public function get_edit(Box_App $app, $id)
    {
        $api = $app->getApiAdmin();
        $model = $api->massmailer_get(array('id'=>$id));
        return $app->render('mod_massmailer_message', array('msg'=>$model));
    }
}