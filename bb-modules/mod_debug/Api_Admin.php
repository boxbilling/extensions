<?php
/**
 * BoxBilling debug module
 */
class Box_Mod_Debug_Api_Admin extends Api_Abstract
{
    /**
     * Clear error log file
     * 
     * @return array
     */
    public function clear_error_log($data)
    {
        $e = BB_PATH_DATA . '/log/php_error.log';
        if(file_exists($e)) {
            $fh = fopen($e, 'w' );
            fclose($fh);
            return true;
        } else {
            return false;
        }
    }
}