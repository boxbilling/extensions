<?php
class Box_Mod_Affiliate_Service
{
    public function install()
    {
        $pdo = Box_Db::getPdo();
        $query="
        CREATE TABLE IF NOT EXISTS `mod_affiliate` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `client_id` int(11) NOT NULL,
        `created_at` varchar(35) NOT NULL,
        `updated_at` varchar(35) NOT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        
        $query="
        CREATE TABLE IF NOT EXISTS `mod_affiliate_commission` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `aff_id` int(11) NOT NULL,
        `payout_id` int(11) NULL,
        `order_id` int(11) NULL,
        `client_id` int(11) NULL,
        `title` varchar(255) NULL,
        `amount` varchar(255) NULL,
        `status` varchar(255) NULL,
        `approved_at` varchar(35) NOT NULL,
        `created_at` varchar(35) NOT NULL,
        `updated_at` varchar(35) NOT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        
        $query="
        CREATE TABLE IF NOT EXISTS `mod_affiliate_payout` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `aff_id` int(11) NOT NULL,
        `transaction_id` varchar(255) NULL,
        `paid_at` varchar(35) NULL,
        `created_at` varchar(35) NOT NULL,
        `updated_at` varchar(35) NOT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ";
        $query="
        CREATE TABLE IF NOT EXISTS `mod_affiliate_statistic` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `aff_id` int(11) NOT NULL,
        `ip` varchar(100) NULL,
        `created_at` varchar(35) NOT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
    }
    
    public function getAffiliateLink($client_id)
    {
        $aff = $this->getAffiliate($client_id);
        return Box_Tools::url('/r/'.$aff->id);
    }
    
    /**
     * Count commision according to type
     * 
     * @param type $price
     * @param type $ct - commision type
     * @param type $cv - commision value
     */
    public function countEarnings($price, $ct, $cv)
    {
        if($ct == 'fixed') {
            return $cv;
        } 
        
        return $price * $cv / 100;
    }
    
    public function signup($client_id)
    {
        $aff = R::dispense('mod_affiliate');
        $aff->client_id = $client_id;
        $aff->created_at = date('c');
        $aff->updated_at = date('c');
        R::store($aff);
        
        $mod = new Box_Mod('affiliate');
        $config = $mod->getConfig();
        $bonus = isset($config['bonus_amount']) ? $config['bonus_amount'] : 0;
        if($bonus > 0) {
            $title = __('Bonus for becoming affiliate');
            $this->addCommision($aff->id, $config['bonus_amount'], $title);
        }
        
        return $aff->id;
    }
    
    public function track($aff_id, $ip)
    {
        $c = R::dispense('mod_affiliate_statistic');
        $c->aff_id = $aff_id;
        $c->ip = $ip;
        $c->created_at = date('c');
        R::store($c);
    }
    
    public function addCommision($aff_id, $amount, $title, $status = 'approved', $order_id = null, $client_id = null)
    {
        $c = R::dispense('mod_affiliate_commission');
        $c->aff_id = $aff_id;
        $c->amount = $amount;
        $c->title = $title;
        $c->order_id = $order_id;
        $c->client_id = $client_id;
        $c->status = $status;
        $c->created_at = date('c');
        $c->updated_at = date('c');
        
        if($status == 'approved') {
            $c->approved_at = date('c');
        }
        
        R::store($c);
    }
    
    public function getAffiliate($client_id)
    {
        $aff = R::findOne('mod_affiliate', 'client_id=:client_id', array('client_id'=>$client_id));
        if(!$aff) {
            throw new Box_Exception('Affiliate not found');
        }
        return $aff;
    }
    
    public function getAffiliateById($aff_id)
    {
        $aff = R::findOne('mod_affiliate', 'id=:id', array('id'=>$aff_id));
        if(!$aff) {
            throw new Box_Exception('Affiliate not found');
        }
        return $aff;
    }
    
    public function isAffiliate($client_id)
    {
        $q="SELECT id FROM mod_affiliate WHERE client_id = :client_id";
        $r = R::getCell($q, array('client_id'=>$client_id));
        return (bool)$r;
    }
    
    public function getTotalEarned($aff_id)
    {
        $q="SELECT SUM(amount)
            FROM mod_affiliate_commission 
            WHERE aff_id = :aff_id
            AND status = 'approved'
            GROUP BY aff_id
        ";
        $r = R::getCell($q, array('aff_id'=>$aff_id));
        if(!$r) {
            return 0;
        }
        return $r;
    }
    
    public function getTotalPending($aff_id)
    {
        $q="SELECT SUM(amount)
            FROM mod_affiliate_commission 
            WHERE aff_id = :aff_id
            AND status = 'pending'
            GROUP BY aff_id
        ";
        $r = R::getCell($q, array('aff_id'=>$aff_id));
        if(!$r) {
            return 0;
        }
        return $r;
    }
    
    public function getTotalPaidout($aff_id)
    {
        $q="SELECT SUM(amount)
            FROM mod_affiliate_commission 
            WHERE payout_id IS NOT NULL 
            AND aff_id = :aff_id
            GROUP BY aff_id
        ";
        $r = R::getCell($q, array('aff_id'=>$aff_id));
        if(!$r) {
            return 0;
        }
        return $r;
    }
    
    /**
     * @todo
     * @param type $aff_id
     * @return type 
     */
    public function getClicks($aff_id)
    {
        return array(5, 2);
    }
    
    public function getNextPaymentDate()
    {
        $curMonth = date('n');
        $curYear  = date('Y');
        if ($curMonth == 12)
            $firstDayNextMonth = mktime(1, 1, 1, 1, 1, $curYear+1);
        else
            $firstDayNextMonth = mktime(0, 0, 0, $curMonth+1, 1);
        
        $time = strtotime("-1 day", $firstDayNextMonth);
        return date('c', $time);
    }
    
    public function toApiArray($row)
    {
        if($row instanceof RedBean_OODBBean) {
            $row = $row->export();
        }
        
        $row['first_name'] = 'sa';
        $row['last_name'] = 'sa';
        
        return $row;
    }
    
    public static function onAfterClientSignUp(Box_Event $event)
    {
        $params = $event->getParameters();
        $client_id = $params['id'];
        
        if (isset($_COOKIE['bbr'])) {
            Box_Db::getRb();
            $client = R::load('client', $client_id);
            $client->referred_by = $_COOKIE['bbr'];
            R::store($client);
        }
    }
    
    public static function onAfterAffiliateSignup(Box_Event $event)
    {
        $api = $event->getApiAdmin();
        $params = $event->getParameters();
        $client_id = $params['client_id'];
        $s = new self();
        $link = $s->getAffiliateLink($client_id);
        
        try {
            $email = array();
            $email['to_client'] = $client_id;
            $email['code']      = 'mod_affiliate_signup';
            $email['referral_link']  = $link;
            $api->email_template_send($email);
        } catch(Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    
    public static function onAfterClientOrderCreate(Box_Event $event)
    {
        $service = new self();
        $params = $event->getParameters();
        $order_id = $params['id'];
        $client_id = $params['client_id'];
        $ref_id = isset($_COOKIE['bbr']) ? (int)$_COOKIE['bbr'] : null;
        
        if ($ref_id) {
            $aff = $service->getAffiliateById($ref_id);
            if($aff->client_id != $client_id) {
                $order = R::load('client_order', $order_id);
                $order->referred_by = $aff->id;
                R::store($order);
                error_log('Order refferer saved');
            } else {
                error_log('Can not reffer yourself');
            }
        } else {
            error_log('Order has no refferer');
        }
    }
    
    public static function onAfterAdminOrderActivate(Box_Event $event)
    {
        $params = $event->getParameters();
        $order = R::load('client_order', $params['id']);
        if(!$order->referred_by) {
            error_log('Order has no refferer upon activation');
            return ;
        }
        
        $mod    = new Box_Mod('affiliate');
        $config = $mod->getConfig();
        
        $price = $order->price - $order->discount;
        $service = new self();
        $amount = $service->countEarnings($price, $config['commission_type'], $config['commission']);
        $title = __('Commision for :order_title', array(':order_title'=>$order->title));
        $service->addCommision($order->referred_by, $amount, $title, 'pending', $order->id);
    }
    
}