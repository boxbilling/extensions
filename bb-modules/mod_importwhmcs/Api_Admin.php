<?php
/**
 * Whmcs import tool
 */
class Box_Mod_Importwhmcs_Api_Admin extends Api_Abstract
{
    public function config_update($data)
    {
        
    }
    
    public function config_get($data)
    {
        return array(
            'hostname'  =>  '',
        );
    }
}