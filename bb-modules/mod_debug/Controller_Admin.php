<?php

class Box_Mod_Debug_Controller_Admin
{
    public function fetchNavigation()
    {
        return array(
            'subpages'=>array(
                array(
                    'location'  => 'extensions',
                    'index'     => 1800,
                    'label'     => 'Debug',
                    'uri'       => '/debug',
                    'class'     => '',
                ),
            ),
        );
    }
    
    public function register(Box_App &$app)
    {
        $app->get('/debug', 'get_index', array(), get_class($this));
        $app->get('/debug/errors', 'get_errors', array(), get_class($this));
        $app->get('/debug/phpinfo', 'get_phpinfo', array(), get_class($this));
        $app->get('/debug/changelog', 'get_changelog', array(), get_class($this));
    }

    public function get_index(Box_App $app)
    {
        $api = $app->getApiAdmin();
        return $app->render('mod_debug_index');
    }
    
    public function get_phpinfo(Box_App $app)
    {
        $api = $app->getApiAdmin();
        
		ob_start();
		phpinfo();
		$p=ob_get_contents();
		ob_end_clean();
        
        return $app->render('mod_debug_raw', array('html'=>$p));
    }
    
    public function get_changelog(Box_App $app)
    {
        $api = $app->getApiAdmin();
        
        $e = BB_PATH_ROOT . '/CHANGELOG.txt';
        if(file_exists($e)) {
            $p = file_get_contents($e);
        } else {
            $p = 'CHANGELOG.txt file not found';
        }
        
        return $app->render('mod_debug_raw', array('pre'=>$p));
    }
    
    public function get_errors(Box_App $app)
    {
        $api = $app->getApiAdmin();
        
        $e = BB_PATH_DATA . '/log/php_error.log';
        if(file_exists($e)) {
            $p = file_get_contents($e);
            if(empty($p)) {
                $p = 'Error log file is empty';
            }
        } else {
            $p = '[404] Error log does not exist';
        }
        
        return $app->render('mod_debug_raw', array('pre'=>$p));
    }
}