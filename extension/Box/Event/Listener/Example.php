<?php
/*
 * Example Event Listener for BoxBilling
 * 
 * This file intended to be located at /box/extension/Box/Event/Listener/ dir
 */
class Box_Event_Listener_Example extends Box_Event_Listener_Abstract
{
    /**
     * Register listener on BoxBilling
     * @return array - key = static method name; value = event name
     */
    public function register()
    {
        return array(
            'emailMe'  =>  'client.postCreateClient',
        );
    }

    /**
     * Notify yourself when new client signs up
     * 
     * @param Box_Event $event
     */
    public static function emailMe(Box_Event $event)
    {
        // returns event name 'client.postCreateClient'
        $event_name = $event->getName();

        // return event subject, for this event it is Model_Client
        $client     = $event->getSubject();

        // return array of additional event parameters.
        $params     = $event->getParameters();

        if(!$client instanceof Model_Client) {
            return;
        }

        $message = sprintf('"%s" just signed up', $client->getFullName());
        mail('your@email.com', $message, $message);
    }
}