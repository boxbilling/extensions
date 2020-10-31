<?php
/**
 * Invite BoxBilling module
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
class Box_Mod_Invite_Controller_Client
{
    public function register(Box_App &$app)
    {
        $app->get('/invite/confirm/:hash',             'get_confirm', array('hash'=>'[a-z0-9]+'), get_class($this));
    }

    public function get_confirm(Box_App $app, $hash)
    {
        $app->getApiGuest()->invite_confirm(array('hash'=>$hash));
        $app->redirect('/login?register=1');
    }
}