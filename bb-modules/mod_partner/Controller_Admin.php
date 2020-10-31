<?php

class Box_Mod_Partner_Controller_Admin
{
    public function fetchNavigation()
    {
        return array(
            'subpages'=>array(
                array(
                    'location'  => 'system',
                    'index'     => 800,
                    'label' => 'Partners',
                    'uri'   => '/partner',
                    'class' => '',
                ),
            ),
        );
    }
    
    public function install()
    {
        Box_Db::getRb();
        $sql="
        CREATE TABLE IF NOT EXISTS `mod_partner` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `client_id` bigint(20) DEFAULT NULL,
            `website` varchar(255) DEFAULT NULL,
            `logo` varchar(255) DEFAULT NULL,
            `product_id` bigint(20) DEFAULT NULL,
            `price` decimal(18,2)	 DEFAULT NULL,
            `public` tinyint	 DEFAULT 0,
            `status` varchar(255)	 DEFAULT 'active',
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `client_id_idx` (`client_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
        R::exec($sql);
    }
    
    public function register(Box_App &$app)
    {
        $app->get('/partner', 'get_index', array(), get_class($this));
        $app->get('/partner/:id', 'get_partner', array('id'=>'[0-9]+'), get_class($this));
    }

    public function get_index(Box_App $app)
    {
        $api = $app->getApiAdmin();
        return $app->render('mod_partner_index');
    }
    
    public function get_partner(Box_App $app, $id)
    {
        $api = $app->getApiAdmin();
        $partner = $api->partner_get(array('client_id'=>$id));
        return $app->render('mod_partner_details', array('partner'=>$partner));
    }
}