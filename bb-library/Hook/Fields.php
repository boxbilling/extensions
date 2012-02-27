<?php
/**
 * Event Hook example to require custom signup fields
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
class Hook_Fields
{
    public static function onBeforeClientSignUp(Box_Event $event)
    {
        $data = $event->getSubject();
        if(!isset($data['country']) || empty($data['country'])) {
            throw new Box_Exception('Please select your country');
        }
    }
}