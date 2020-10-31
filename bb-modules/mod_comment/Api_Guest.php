<?php
/**
 * Comments management
 */
class Box_Mod_Comment_Api_Guest extends Api_Abstract
{
    /**
     * Get comment extension public configuration
     * 
     * @return array
     */
    public function params()
    {
        $mod = new Box_Mod('comment');
        $config = $mod->getConfig();
        
        return array(
            'facebook_enabled'      =>  $config['facebook_enabled'],
            'facebook_num_posts'    =>  $config['facebook_num_posts'],
            'facebook_width'        =>  $config['facebook_width'],
            
            'disqus_enabled'        =>  $config['disqus_enabled'],
            'disqus_shortname'      =>  $config['disqus_shortname'],
            
            'custom_enabled'            =>  $config['custom_enabled'],
            'custom_script'             =>  $config['custom_script'],
        );
    }
}