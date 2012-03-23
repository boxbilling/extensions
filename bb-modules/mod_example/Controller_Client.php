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
 * This file connects BoxBilling client area interface and API
 */
class Box_Mod_Example_Controller_Client
{
    /**
     * Methods maps client areas urls to corresponding methods
     * Always use your module prefix to avoid conflicts with other modules
     * in future
     *
     * @param Box_App $app - returned by reference
     */
    public function register(Box_App &$app)
    {
        $app->get('/example',             'get_index', array(), get_class($this));
        $app->get('/example/protected',   'get_protected', array(), get_class($this));
    }

    public function get_index(Box_App $app)
    {
        $api = $app->getApiGuest();
        return $app->render('mod_example_index');
    }

    public function get_protected(Box_App $app)
    {
        // call $app->getApiClient() method to validate if client is logged in
        $api = $app->getApiClient();
        return $app->render('mod_example_index', array('show_protected'=>true));
    }
}