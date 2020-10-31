<?php

class Box_Mod_Droidtweak_Controller_Admin
{
    public function fetchNavigation()
    {
        return array(
            'subpages' => array(
                array(
                    'location'  => 'order',
                    'label' => 'DroidTweak',
                    'uri' => 'droidtweak',
                    'index'     => 10,
                    'class'     => '',
                ),
            ),
        );
    }
    
    public function register(Box_App &$app)
    {
        $app->get('/droidtweak',           'get_index', array(), get_class($this));
        $app->get('/droidtweak/review/:id','get_review', array('id'=>'[0-9]+'), get_class($this));
    }

    public function get_index(Box_App $app)
    {
        $app->getApiAdmin();
        return $app->render('mod_droidtweak_index');
    }
    
    public function get_review(Box_App $app, $id)
    {
        $api = $app->getApiAdmin();
        $review = $api->droidtweak_review_get(array('id'=>$id));
        return $app->render('mod_droidtweak_review', array('review'=>$review));
    }
}