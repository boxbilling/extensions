<?php
/**
 * Example BoxBilling module
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

/**
 * This file connects BoxBilling amin area interface and API
 */
class Box_Mod_Example_Controller_Admin
{
    /**
     * This method registers menu items in admin area navigation block
     * This navigation is cached in bb-data/cache/{hash}. To see changes please
     * remove the file
     * 
     * @return array
     */
    public function fetchNavigation()
    {
        return array(
            'group'  =>  array(
                'index'     => 1500,                // menu sort order
                'location'  =>  'example',          // menu group identificator for subitems
                'label'     => 'Example module',    // menu group title
                'class'     => 'example',           // used for css styling menu item
            ),
            'subpages'=> array(
                array(
                    'location'  => 'example', // place this module in extensions group
                    'label'     => 'Example module submenu',
                    'index'     => 1500,
                    'uri'       => 'example',
                    'class'     => '',
                ),
            ),
        );
    }

    /**
     * Return info about module
     */
    public function getInfo()
    {
        return array(
            'title'         =>  'Example BoxBilling extension',
            'description'   =>  'This is a dummy extension for developer to get started',
            'uri'           =>  'http://github.com/boxbilling/',
            'author'        =>  'BoxBilling',
            'author_uri'    =>  'http://extensions.boxbilling.com/',
            'version'       =>  '0.1.1',
            'license'       =>  'GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html',
        );
    }
    
    /**
     * Method to install module
     *
     * @return bool
     */
    public function install()
    {
        // execute sql script if needed
        $pdo = Box_Db::getPdo();
        $query="SELECT NOW()";
        $stmt = $pdo->prepare($query);
        $stmt->execute();

        //throw new Box_Exception("Throw exception to terminate module installation process with a message", array(), 123);
        return true;
    }
    
    /**
     * Method to uninstall module
     * 
     * @return bool
     */
    public function uninstall()
    {
        //throw new Box_Exception("Throw exception to terminate module uninstallation process with a message", array(), 124);
        return true;
    }

    /**
     * Methods maps admin areas urls to corresponding methods
     * Always use your module prefix to avoid conflicts with other modules
     * in future
     *
     *
     * @example $app->get('/example/test',      'get_test', null, get_class($this)); // calls get_test method on this class
     * @example $app->get('/example/:id',        'get_index', array('id'=>'[0-9]+'), get_class($this));
     * @param Box_App $app
     */
    public function register(Box_App &$app)
    {
        $app->get('/example',             'get_index', array(), get_class($this));
        $app->get('/example/test',        'get_test', array(), get_class($this));
        $app->get('/example/user/:id',    'get_user', array('id'=>'[0-9]+'), get_class($this));
        $app->get('/example/api',         'get_api', array(), get_class($this));
    }

    public function get_index(Box_App $app)
    {
        // always call this method to validate if admin is logged in
        $api = $app->getApiAdmin();
        return $app->render('mod_example_index');
    }

    public function get_test(Box_App $app)
    {
        // always call this method to validate if admin is logged in
        $api = $app->getApiAdmin();

        $params = array();
        $params['youparamname'] = 'yourparamvalue';

        return $app->render('mod_example_index', $params);
    }

    public function get_user(Box_App $app, $id)
    {
        // always call this method to validate if admin is logged in
        $api = $app->getApiAdmin();

        $params = array();
        $params['userid'] = $id;
        return $app->render('mod_example_index', $params);
    }
    
    public function get_api(Box_App $app, $id)
    {
        // always call this method to validate if admin is logged in
        $api = $app->getApiAdmin();
        $list_from_controller = $api->example_get_something();

        $params = array();
        $params['api_example'] = true;
        $params['list_from_controller'] = $list_from_controller;

        return $app->render('mod_example_index', $params);
    }
}