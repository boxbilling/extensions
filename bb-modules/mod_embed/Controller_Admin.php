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
class Box_Mod_Embed_Controller_Admin
{
    public function fetchNavigation()
    {
        return array(
            'subpages'=> array(
                array(
                    'location'  => 'extensions',
                    'label'     => 'Embed and integrate',
                    'index'     => 1500,
                    'uri'       => 'embed',
                    'class'     => '',
                ),
            ),
        );
    }

    public function register(Box_App &$app)
    {
        $app->get('/embed',             'get_index', array(), get_class($this));
    }

    public function get_index(Box_App $app)
    {
        $api = $app->getApiAdmin();
        return $app->render('mod_embed_index');
    }
}