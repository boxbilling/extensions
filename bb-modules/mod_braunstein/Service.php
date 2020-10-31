<?php

class Box_Mod_Braunstein_Service
{
    public static function onBeforeClientSignUp(Box_Event $event)
    {
        $mod = new Box_Mod('braunstein');
        $config = $mod->getConfig();
        $required_params = $config['required'];
        
        $params = $event->getParameters();

        foreach($required_params as $p=>$required) {
            if($required) {
                if(!isset($params[$p]) || empty($params[$p])) {
                    throw new Box_Exception('It is required that you provide details for field ":field"', array(':field'=>$p));
                }
            }
        }
    }
}