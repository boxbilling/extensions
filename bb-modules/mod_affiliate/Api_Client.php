<?php
/**
 * Affiliate BoxBilling module
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
class Box_Mod_Affiliate_Api_Client extends Api_Abstract
{
    private $service = null;
    
    protected function init()
    {
        Box_Db::getRb();
        $this->service = new Box_Mod_Affiliate_Service();
    }
    
    /**
     * Clients affiliate link
     * 
     * @return string or null if not registered as 
     */
    public function link()
    {
        return $this->service->getAffiliateLink($this->_identity->id);
    }
    
    /**
     * Sign up for affiliate program
     * 
     * @return int 
     */
    public function signup()
    {
        if($this->is_registered()) {
            $aff = $this->service->getAffiliate($this->_identity->id);
            return (int)$aff->id;
        }
        
        $api = $this->getApiAdmin();
        
        $api->hook_call(array('event'=>'onBeforeAffiliateSignup', 'params'=>array('client_id'=>$this->_identity->id)));
        
        $id = $this->service->signup($this->_identity->id);
        
        $api->hook_call(array('event'=>'onAfterAffiliateSignup', 'params'=>array('client_id'=>$this->_identity->id, 'id'=>$id)));
        
        return (int)$id;
    }
    
    /**
     * Check if currently logged in client is an affiliate
     * 
     * @return boolean 
     */
    public function is_registered()
    {
        return $this->service->isAffiliate($this->_identity->id);
    }
    
    /**
     * Get reffered clients list
     * 
     * @return array
     */
    /*
    public function referred_clients()
    {
        $aff = $this->service->getAffiliate($this->_identity->id);
        $sql = "
            SELECT c.id, CONCAT(SUBSTRING(c.first_name,1,1), '.', c.last_name) as name,
            c.created_at
            FROM client c
            WHERE c.referred_by = :referred_by
            AND c.status = 'active'
            GROUP BY c.id
            ORDER BY c.id DESC
            LIMIT 100
        ";
        
        $params = array();
        $params['referred_by'] = $aff->id;
        
        $list = R::getAll($sql, $params);

        return $list;
    }
    */

    /**
     * Get list of payouts
     * 
     * @return array 
     */
    public function get_payments()
    {
        $aff = $this->service->getAffiliate($this->_identity->id);
        
        return array(
            array(
                'id'  =>1,
                'transaction_id'  =>'asdasd12312',
                'amount'  =>'55',
                'created_at'  =>date('c'),
                'currency'  =>'USD',
            )
        );
    }

    /**
     * Get list of earnings
     * 
     * @return array 
     */
    public function earnings()
    {
        $aff = $this->service->getAffiliate($this->_identity->id);
        $sql = "
            SELECT title, amount, status, created_at
            FROM mod_affiliate_commission
            WHERE aff_id = :aff_id
            ORDER BY id DESC
            LIMIT 100
        ";
        return R::getAll($sql, array('aff_id'=>$aff->id));
    }
    
    /**
     * Get affiliate stats 
     * 
     * @return type 
     */
    public function stats()
    {
        $aff = $this->service->getAffiliate($this->_identity->id);
        
        $mod = new Box_Mod('affiliate');
        $config = $mod->getConfig();
        
        $earned = $this->service->getTotalEarned($aff->id);
        $paid = $this->service->getTotalPaidout($aff->id);
        $pending = $this->service->getTotalPending($aff->id);
        
        return array(
            'next_payment_date'     =>  $this->service->getNextPaymentDate(),
            'min_withdraw_amount'   =>  isset($config['min_withdraw_amount']) ? $config['min_withdraw_amount'] : null,
            'total_earned'          =>  $earned,
            'total_pending'         =>  $pending,
            'total_paid'            =>  $paid,
            'total_unpaid'          =>  $earned - $paid,
        );
    }
}