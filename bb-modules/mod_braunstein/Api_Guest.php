<?php
/**
 * Braunstein module
 */
class Box_Mod_Braunstein_Api_Guest extends Api_Abstract
{
    /**
     * Get required fields for client signup form
     * 
     * @return array
     */
    public function required($data)
    {
        $mod = new Box_Mod('braunstein');
        $config = $mod->getConfig();
        return $config['required'];
    }
}