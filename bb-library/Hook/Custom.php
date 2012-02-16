<?php
/**
 * Custom Event Hook example class
 *
 * To connect it to BoxBilling put it to bb-library/Hook/Custom.php
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
class Hook_Custom
{
    public static function onAfterOrderActivate(Box_Event $event)
    {
        $order = $event->getSubject();
        $plugin = $order->Product->plugin;
        if($plugin == 'MyPlugin') {
            // init plugin class
            // do something with plugin on order activation action
        }
    }

    public static function onAfterOrderRenew(Box_Event $event)
    {

    }

    public static function onAfterOrderSuspend(Box_Event $event)
    {

    }

    public static function onAfterOrderUnsuspend(Box_Event $event)
    {

    }

    public static function onAfterOrderCancel(Box_Event $event)
    {

    }

    public static function onAfterOrderUncancel(Box_Event $event)
    {

    }

    public static function onAfterOrderDelete(Box_Event $event)
    {

    }
}