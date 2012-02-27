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

/**
 * This module can be called from API or in template
 * 
 * @example Use this code in any BoxBilling template:
 * 
 *  {% set feed = guest.example_top_songs %}
 *  {% debug feed %}
 *  Top 3 songs: 
 *      {{ feed[8]._children[7]._text|raw }}
 *      {{ feed[9]._children[7]._text|raw }}
 *      {{ feed[10]._children[7]._text|raw }}
 */
class Box_Mod_Example_Api_Guest extends Api_Abstract
{
    /**
     * Get Top Songs from iTunes Store
     * @param int $limit - define songs limit
     */
    public function top_songs($data)
    {
        $limit = isset($data['limit']) ? $data['limit'] : 10;
        $feed = "http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/ws/RSS/topsongs/limit=$limit/xml";
        $top_songs = file_get_contents($feed);
        $xml = simplexml_load_string($top_songs);
        $array = array();
        $this->_convertXmlObjToArr($xml, $array);
        return $array;
    }
    
    private function _convertXmlObjToArr($obj, &$arr)
    { 
        $children = $obj->children(); 
        foreach ($children as $elementName => $node) 
        { 
            $nextIdx = count($arr); 
            $arr[$nextIdx] = array(); 
            $arr[$nextIdx]['_name'] = strtolower((string)$elementName);
            $arr[$nextIdx]['_attributes'] = array();
            $attributes = $node->attributes(); 
            foreach ($attributes as $attributeName => $attributeValue) 
            { 
                $attribName = strtolower(trim((string)$attributeName)); 
                $attribVal = trim((string)$attributeValue); 
                $arr[$nextIdx]['_attributes'][$attribName] = $attribVal;
            } 
            $text = (string)$node; 
            $text = trim($text); 
            if (strlen($text) > 0) 
            { 
                $arr[$nextIdx]['_text'] = $text;
            } 
            $arr[$nextIdx]['_children'] = array();
            $this->_convertXmlObjToArr($node, $arr[$nextIdx]['_children']);
        } 
        return; 
    }  
}