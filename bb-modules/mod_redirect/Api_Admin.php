<?php
/**
 * Redirects management
 */
class Box_Mod_Redirect_Api_Admin extends Api_Abstract
{
    
    /**
     * Get list of redirects
     * 
     * @return array - list
     */
    public function get_list($data)
    {
        $sql='
            SELECT id, meta_key as path, meta_value as target
            FROM extension_meta
            WHERE extension = "mod_redirect"
            ORDER BY id ASC
        ';
        $list = R::getAll($sql);
        return $list;
    }
    
    /**
     * Get redirect by id
     * 
     * @param int $id - int
     * 
     * @return array
     */
    public function get($data)
    {
        if(!isset($data['id'])) {
            throw new Box_Exception('Redirect id not passed');
        }
        
        $bean = $this->_getRedirect($data['id']);
        return array(
            'id'        =>  $bean->id,
            'path'      =>  $bean->meta_key,
            'target'    =>  $bean->meta_value,
        );
    }
    
    /**
     * Create new redirect
     * 
     * @param string $path - redirect path
     * @param string $target - redirect target
     * 
     * @return int redirect id
     */
    public function create($data)
    {
        if(!isset($data['path'])) {
            throw new Box_Exception('Redirect path not passed');
        }
        
        if(!isset($data['target'])) {
            throw new Box_Exception('Redirect target not passed');
        }
        
        $bean = R::dispense('extension_meta');
        $bean->extension = 'mod_redirect';
        $bean->meta_key = $data['path'];
        $bean->meta_value = $data['target'];
        $bean->created_at = date('c');
        $bean->updated_at = date('c');
        R::store($bean);
        
        $id = $bean->id;
        
        $this->_log('Created new redirect #%s', $id);
        return $id;
    }
    
    /**
     * Update redirect 
     * 
     * @param int $id - redirect id
     * 
     * @param string $path - redirect path
     * @param string $target - redirect target
     * 
     * @return true
     */
    public function update($data)
    {
        if(!isset($data['id'])) {
            throw new Box_Exception('Redirect id not passed');
        }
        
        if(!isset($data['path'])) {
            throw new Box_Exception('Redirect path not passed');
        }
        
        if(!isset($data['target'])) {
            throw new Box_Exception('Redirect target not passed');
        }
        
        $bean = $this->_getRedirect($data['id']);
        $bean->meta_key = $data['path'];
        $bean->meta_value = $data['target'];
        $bean->updated_at = date('c');
        R::store($bean);
        
        $this->_log('Updated redirect #%s', $data['id']);
        return true;
    }
    
    /**
     * Delete redirect 
     * 
     * @param int $id - redirect id
     * @return true
     */
    public function delete($data)
    {
        if(!isset($data['id'])) {
            throw new Box_Exception('Redirect id not passed');
        }
        
        $bean = $this->_getRedirect($data['id']);
        R::trash($bean);
        
        $this->_log('Removed redirect #%s', $data['id']);
        return true;
    }
    
    private function _getRedirect($id)
    {
        $sql = " extension = 'mod_redirect' AND id = :id";
        $values = array('id'=>$id);
        $bean = R::findOne('extension_meta',$sql, $values); 
        
        if(!$bean) {
            throw new Box_Exception('Redirect not found');
        }
        
        return $bean;
    }
}