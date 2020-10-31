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
class Box_Mod_Affiliate_Api_Admin extends Api_Abstract
{
    protected function init()
    {
        Box_Db::getRb();
        $mod = new Box_Mod('affiliate');
        $this->service = $mod->getService();
    }
    
    /**
     * Get affiliates list
     * 
     * @param int $page - page for pagination
     * @param int $per_page - rows per page for pagination
     * 
     * @return array
     */
    public function get_list($data)
    {
        $sql="SELECT a.id, a.created_at, c.first_name, c.last_name, c.email, COUNT(st.id) as clicks
            FROM mod_affiliate a
            LEFT JOIN client c ON (c.id = a.client_id)
            LEFT JOIN mod_affiliate_statistic st ON (st.aff_id = a.id)
            GROUP BY st.aff_id
            ORDER BY a.id DESC
        ";
        return Box_Db::paginateQuery($sql, null, $data);
    }
    
    /**
     * Get affiliate by id
     * 
     * @param int $id - affilliate id
     * 
     * @return array
     */
    public function get($data)
    {
        $aff = $this->service->getAffiliateById($data['id']);
        return $this->service->toApiArray($aff);
    }
    
    /**
     * Get affiliate stats 
     * 
     * @param int $id - affilliate id
     * 
     * @return type 
     */
    public function stats($data)
    {
        $aff = $this->service->getAffiliateById($data['id']);
        
        $mod = new Box_Mod('affiliate');
        $config = $mod->getConfig();
        
        $earned = $this->service->getTotalEarned($aff->id);
        $paid = $this->service->getTotalPaidout($aff->id);
        $pending = $this->service->getTotalPending($aff->id);
        list($clicks, $unique_clicks) = $this->service->getClicks($aff->id);
        
        return array(
            'next_payment_date'     =>  $this->service->getNextPaymentDate(),
            'min_withdraw_amount'   =>  isset($config['min_withdraw_amount']) ? $config['min_withdraw_amount'] : null,
            'total_earned'          =>  $earned,
            'total_pending'         =>  $pending,
            'total_paid'            =>  $paid,
            'total_unpaid'          =>  $earned - $paid,
            'reffered_clients'      =>  '', //@todo
            'reffered_orders'       =>  '', //@todo
            'clicks'                =>  $clicks,
            'unique_clicks'         =>  $unique_clicks,
        );
    }
    
    /**
     * Get statuses of commissions
     * 
     * @return array
     */
    public function get_statuses()
    {
        $sql="SELECT c.status, COUNT(c.id) as counter
            FROM mod_affiliate_commission c
            GROUP BY c.status
        ";
        $data = R::getAssoc($sql);
        return array(
            'total' =>  array_sum($data),
            'approved' =>  isset($data['approved']) ? $data['approved'] : 0,
            'pending' =>  isset($data['pending']) ? $data['pending'] : 0,
        );
    }
    
    /**
     * Get commissions list
     * 
     * @param int $page - page for pagination
     * @param int $per_page - rows per page for pagination
     * 
     * @return array
     */
    public function get_commissions($data)
    {
        $params = array();
        $sql="SELECT c.*
            FROM mod_affiliate_commission c
            WHERE 1 
        ";
        
        $aff_id = isset($data['aff_id']) ? (int)$data['aff_id'] : null;
        
        if($aff_id) {
            $sql .= ' AND c.aff_id = :aff_id';
            $params['aff_id'] = $aff_id;
        }
        
        $sql .= ' ORDER BY c.id DESC;';
        return Box_Db::paginateQuery($sql, $params, $data);
    }

    
    /**
     * Calculate which orders passed admission period
     * and can be approved for payout.
     * 
     */
    public function batch_issue_commissions()
    {
        $mod = new Box_Mod('affiliate');
        $service = $mod->getService(); 
        $config = $mod->getConfig();
        $wait_days = isset($config['wait_days']) ? $config['wait_days'] : 30;
        
        $sql = "
            SELECT order_id
            FROM mod_affiliate_commission 
            WHERE payout_id > 0
        ";
        
        $paidout_orders = R::getAll($sql);

        $sql = "
            SELECT o.id, o.referred_by, o.title, (o.price - o.discount) as price
            FROM client_order o 
            WHERE o.referred_by > 0
            AND o.status = 'active'
            AND DATEDIFF(o.activated_at, NOW()) > :wait_days
            AND o.id NOT IN (:ids)
            ORDER BY o.id DESC
        ";
        
        $approved_orders = R::getAll($sql, array('wait_days'=>$wait_days, 'ids'=>implode(',',$paidout_orders)));
        foreach($approved_orders as $data) {
            $aff_id = $data['referred_by'];
            $amount = $data['price'];
            $title = __('Commision for :order_title', array(':order_title'=>$data['title']));
            $order_id = $data['id'];
            $service->addCommision($aff_id, $amount, $title, null, $order_id);
            error_log('Issued commision');
        }
        
        return true;
    }
}