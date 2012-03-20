<?php
/**
 * Example module API
 * This api can be access only for admins
 */
class Box_Mod_Example_Api_Admin extends Api_Abstract
{
    /**
     * Return list of example objects
     * 
     * @return array
     */
    public function get_something($data)
    {
        $result = array(
            'apple',
            'google',
        );

        if(isset($data['microsoft'])) {
            $result[] = 'microsoft';
        }
        
        return $result;
    }
}