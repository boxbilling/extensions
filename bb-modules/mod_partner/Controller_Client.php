<?php

class Box_Mod_Partner_Controller_Client
{
    public function register(Box_App &$app)
    {
        $app->get('/partner', 'get_index', array(), get_class($this));
    }

    public function get_index(Box_App $app)
    {
        return $app->render('mod_partner_index');
    }
}