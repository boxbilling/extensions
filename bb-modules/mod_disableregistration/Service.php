<?php

class Box_Mod_Disableregistration_Service
{
    public static function onBeforeClientSignUp(Box_Event $event)
    {
        throw new Box_Exception('No new registrations are accepted at the moment');
    }
    
}