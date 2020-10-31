<?php
/**
 * Example BoxBilling module
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
class Box_Mod_Droidtweak_Api_Admin extends Api_Abstract
{
    /**
     * get list of orders
     */
    public function get_list($data)
    {
        $service = new Box_Mod_Droidtweak_Service();
        $list = Box_Db::getPagerResultSet($data, $service, $this->_role);
        return $list;
    }
    
    public function review_get($data)
    {
        if(!isset($data['id'])) {
            throw new Exception('Review id is missing');
        }
        $service = new Box_Mod_Droidtweak_Service();
        $review = $service->getReview($data['id']);
        return $review;
    }
    
    public function review_approve($data)
    {
        if(!isset($data['id'])) {
            throw new Exception('Review id is missing');
        }
        
        if(!isset($data['title']) || empty($data['title'])) {
            throw new Exception('Review title is missing');
        }
        
        $service = new Box_Mod_Droidtweak_Service();
        $review = $service->getReview($data['id']);
        
        $this->_postToForum($review['content'], $data['title']);
        
        $r = array('status' => 'Approved');
        $service->updateReview($data['id'], $r);
        
        return true;
    }
    
    public function review_update($data)
    {
        if(!isset($data['id'])) {
            throw new Exception('Review id is missing');
        }
        if(!isset($data['content'])) {
            throw new Exception('Review content is missing');
        }
        $service = new Box_Mod_Droidtweak_Service();
        $service->updateReview($data['id'], $data);
        return true;
    }
    
    /**
     * @see http://community.invisionpower.com/resources/documentation/index.html/_/developer-resources/miscellaneous-articles/xml-rpc-api-r246
     * @param type $content
     * @throws Exception 
     */
    private function _postToForum($content, $title = null)
    {
        $mod = new Box_Mod('droidtweak');
        $config = $mod->getConfig();
        
        require_once BB_PATH_MODS . '/mod_droidtweak/classXmlRpc.php';
        $endpoint = $config['ipb_endpoint'];
        $params = array(
            'api_key'       =>  $config['ipb_api_key'],
            'api_module'    =>  'ipb',
        );
        
        define( 'IPS_XML_RPC_DEBUG_ON'  , 0 );
        define( 'IPS_XML_RPC_DEBUG_FILE', '' );
        $xmlrpc    = new classXmlRpc();
        
        //check api key
        $xmlrpc->sendXmlRpc($endpoint, 'helloBoard', $params);
        if($xmlrpc->errors) {
            throw new Exception($xmlrpc->errors[0]);
        }
        
        //check member exists
        $data = array_merge($params, array(
            'search_type'   =>  'email',
            'search_string' =>  $config['ipb_member_email'],
        ));
        $r = $xmlrpc->sendXmlRpc($endpoint, 'checkMemberExists', $data);
        if($xmlrpc->errors) {
            throw new Exception($xmlrpc->errors[0]);
        }
        $exists = (bool)$r['methodResponse']['params']['param']['value']['struct']['member']['value']['boolean'];
        if(!$exists) {
            throw new Exception(sprintf('Forum member with email %s does not exist', $config['ipb_member_email']));
        }
        
        // post new topic
        $topic_title = is_null($title) ? $config['ipb_topic_title'] : $title;
        $data = array_merge($params, array(
            'member_field'  =>  'email',
            'member_key'    =>  $config['ipb_member_email'],
            'forum_id'      =>  $config['ipb_forum_id'],
            'topic_title'   =>  $topic_title,
            'post_content'  =>  $content,
        ));
        $r = $xmlrpc->sendXmlRpc($endpoint, 'postTopic', $data);
        if($xmlrpc->errors) {
            throw new Exception($xmlrpc->errors[0]);
        }
    }
}