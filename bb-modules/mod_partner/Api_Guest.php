<?php
/**
 * Partner program
 */
class Box_Mod_Partner_Api_Guest extends Api_Abstract
{
    /**
     * Get list of partners who allows to be listed in public
     * 
     * @return array
     */
    public function get_list($data)
    {
        $data['only_public'] = true;
        $data['status'] = 'active';
        $service = new Box_Mod_Partner_Service();
        return Box_Db::getPagerResultSet($data, $service, $this->_role);
    }
}