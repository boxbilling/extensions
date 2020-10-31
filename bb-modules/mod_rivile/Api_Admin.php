<?php
class Box_Mod_Rivile_Api_Admin extends Api_Abstract
{   
    public function init()
    {
        require_once dirname(__FILE__) . '/php-excel.class.php';
    }
    
    /**
     * Get information about client my inviter
     * 
     * @optional int $month - month. Default. this month
     * @optional int $year - year. Default. This year
     * 
     * @return mixed
     */
    public function export($data)
    {
        $year = isset($data['year']) ? (int)$data['year'] : date('Y');
        $month = isset($data['month']) ? (int)$data['month'] : date('m');
        $month = '08';
        
        $values = array(
            'paid_at'   =>  $year.'-'.$month.'%',
        );
        
        $sql="
            SELECT i.*, 
                ii.id as line_id, 
                ii.title as line_title, 
                ii.quantity as line_quantity,
                ii.unit as line_unit, 
                ii.price as line_price, 
                ii.taxed as line_taxed
            FROM  `invoice` i
            LEFT JOIN invoice_item ii ON (i.id = ii.invoice_id)
            WHERE i.status = 'paid'
            AND i.paid_at LIKE :paid_at
            AND i.id NOT IN (6697,6199,6207,6208,6209,6217,6233,6310,6788)
            ORDER BY i.paid_at ASC
        ";
        
        $invoices = R::getAll($sql, $values);

        $header = array(
            'I06_kodas_po',
            'I06_op_tip',
            'I06_val_poz',
            'I06_pvm_tip',
            'I06_op_storno',
            'I06_dok_nr',
            'I06_op_data',
            'I06_dok_data',
            'I06_kodas_ms',
            'I06_kodas_ks',
            'I06_kodas_ss',
            'I06_pav',
            'I06_adr',
            'I06_atstovas',
            'I06_kodas_vs',
            'I06_pav2',
            'I06_adr2',
            'I06_adr3',
            'I06_kodas_vl',
            'I06_kodas_xs',
            'I06_kodas_ss_p',
            'I06_pastabos',
            'I06_mok_dok',
            'I06_mok_suma',
            'I06_kodas_ss_m',
            'I06_suma_val',
            'I06_suma',
            'I06_suma_pvm',
            'I06_kursas',
            'I06_perkelta',
            'I06_addusr',
            'I06_r_date',
            'I06_useris',
            'I06_kodas_au',
            'I06_kodas_sm',
            'I06_intrastat',
            'I06_dok_reg',
            'I06_kodas_ak',
            'I06_web_poz',
            'I06_web_atas',
            'I06_web_perkelta',
            'Ppc_perkelta',
            'I07_kodas_po',
            'I07_eil_nr',
            'I07_tipas',
            'I07_kodas',
            'I07_pav',
            'I07_kodas_tr',
            'I07_kodas_is',
            'I07_kodas_os',
            'I07_kodas_os_c',
            'I07_serija',
            'I07_kodas_us',
            'I07_kiekis',
            'I07_frakcija',
            'I07_kodas_us_p',
            'I07_kodas_us_a',
            'I07_alt_kiekis',
            'I07_alt_frak',
            'I07_val_kaina',
            'I07_suma_val',
            'I07_kaina_be',
            'I07_kaina_su',
            'I07_nuolaida',
            'I07_islaidu_m',
            'I07_islaidos',
            'I07_islaidos_pvm',
            'I07_muitas_m',
            'I07_muitas',
            'I07_muitas_pvm',
            'I07_akcizas_m',
            'I07_akcizas',
            'I07_akcizas_pvm',
            'I07_mokestis',
            'I07_mokestis_p',
            'I07_pvm',
            'I07_suma',
            'I07_par_kaina',
            'I07_par_kaina_n',
            'I07_mok_suma',
            'I07_savikaina',
            'I07_galioja_iki',
            'I07_perkelta',
            'I07_r_date',
            'I07_useris',
            'T_pav',
            'T_kiekis',
            'Di07_bar_kodas',
            'I07_sertifikatas',
            'I07_kodas_kt',
            'I07_kodas_k0',
            'I07_addusr',
            'I07_apskritis',
            'I07_sandoris',
            'I07_salygos',
            'I07_rusis',
            'I07_salis',
            'I07_matas',
            'I07_salis_k',
            'I07_mase',
            'I07_int_kiekis',
        );

        $xls = new Excel_XML('UTF-8', false);
        $xls->addRow($header);
        foreach($invoices as $invoice) {
            $nr = $invoice['serie'] . $invoice['nr'];
            $total = $invoice['line_price'] * $invoice['line_quantity'];
            
            $xls->addRow(array(
            '', //'I06_kodas_po',
            '', //'I06_op_tip',
            '', //'I06_val_poz',
            '', //'I06_pvm_tip',
            '', //'I06_op_storno',
            $invoice['id'], //'I06_dok_nr', - dokumento numeris
            '', //'I06_op_data',
            $invoice['paid_at'], //'I06_dok_data', - dokumento data
            '', //'I06_kodas_ms',
            $invoice['client_id'], //'I06_kodas_ks', - kliento kodas
            '', //'I06_kodas_ss',
            'Invoice', //'I06_pav', - dokumento pavadinimas
            '', //'I06_adr',
            '', //'I06_atstovas',
            '', //'I06_kodas_vs',
            '', //'I06_pav2',
            '', //'I06_adr2',
            '', //'I06_adr3',
            '', //'I06_kodas_vl',
            '', //'I06_kodas_xs',
            '', //'I06_kodas_ss_p',
            '', //'I06_pastabos',
            '', //'I06_mok_dok',
            0, //'I06_mok_suma', - mokesčio suma, visada bus 0, kol tapsit PVM mokėtojais
            '', //'I06_kodas_ss_m',
            '', //'I06_suma_val', - suma valiuta
            '', //'I06_suma', suma litais
            0, //'I06_suma_pvm', - suma PVM, tai bus 0
            '', //'I06_kursas', - valiutos kursas (kursas imamas kiekvienos dienos pagal nustatytą buh.kursą)
            '', //'I06_perkelta',
            '', //'I06_addusr',
            '', //'I06_r_date',
            '', //'I06_useris',
            '', //'I06_kodas_au',
            '', //'I06_kodas_sm',
            '', //'I06_intrastat',
            $nr, //'I06_dok_reg', - dokumento numeris
            '', //'I06_kodas_ak',
            '', //'I06_web_poz',
            '', //'I06_web_atas',
            '', //'I06_web_perkelta',
            '', //'Ppc_perkelta',
            '', //'I07_kodas_po',
            $invoice['line_id'], //'I07_eil_nr', - eilutės numeris dokumente
            '', //'I07_tipas',
            '', //'I07_kodas', - paslaugos kodas
            $invoice['line_title'], //'I07_pav',- paslaugos pavadinimas
            '', //'I07_kodas_tr',
            '', //'I07_kodas_is',
            '', //'I07_kodas_os',
            '', //'I07_kodas_os_c',
            $invoice['serie'], //'I07_serija',
            'vnt.', //'I07_kodas_us', - vnt. (matavimo vienetas)
            $invoice['line_quantity'], //'I07_kiekis', - kiekis
            1, //'I07_frakcija', - jei vnt, tai 1
            '', //'I07_kodas_us_p', - vnt. (matavimo vienetas)-
            '', //'I07_kodas_us_a', - vnt. (matavimo vienetas)
            '', //'I07_alt_kiekis', - kiekis
            '', //'I07_alt_frak', - jei vnt, tai 1
            $invoice['lince_price'], //'I07_val_kaina', - kaina valiuta
            $total, //'I07_suma_val', - suma valiuta
            $total, //'I07_kaina_be', - kaina be PVM
            $total, //'I07_kaina_su', - kaina su PVM
            0, //'I07_nuolaida', - bus visada 0, jei nebus nuolaidos
            0, //'I07_islaidu_m',- bus visada 0
            0, //'I07_islaidos',- bus visada 0
            0, //'I07_islaidos_pvm',- bus 0, kol netapsim PVM mokėtojais
            '', //'I07_muitas_m',
            '', //'I07_muitas',
            '', //'I07_muitas_pvm',
            '', //'I07_akcizas_m',
            '', //'I07_akcizas',
            '', //'I07_akcizas_pvm',
            '', //'I07_mokestis',
            '', //'I07_mokestis_p',
            '', //'I07_pvm',
            '', //'I07_suma', - suma litais
            ));
        }
        
        $company = $this->getApiGuest()->system_company();
        $title = strtolower($company['name'].'_'.$year . '_'. $month);
        $title = str_replace(',', '_', $title);
        $xls->generateXML($title);
        exit;
    }
    
    /**
     * Download xls of transactions
     * 
     * @param int $gateway  - payment gateway id
     * @param int $year     - year
     * @param int $month    - month
     */
    public function get_transactions($data)
    {
        $gateway = isset($data['gateway']) ? (int)$data['gateway'] : date('Y');
        $year = isset($data['year']) ? (int)$data['year'] : date('Y');
        $month = isset($data['month']) ? (int)$data['month'] : date('m');
        
        $sql = "SELECT ipn, created_at
            FROM transaction 
            WHERE gateway_id = :gateway_id 
            AND DATE_FORMAT(`created_at`, '%Y-%c') = :date
        ";
        $filter = array('gateway_id'=>$gateway, 'date'=>$year.'-'.$month);
        $res = R::getAll($sql, $filter);
        
        $xls = new Excel_XML('UTF-8', false);
        foreach($res as $d) {
            $ipn = json_decode($d['ipn'], 1);
            
            $first = array(
                'created_at' => $d['created_at'],
            );
            $get = $ipn['get'];
            $post = $ipn['post'];
            
            $data = array_merge($first, $get, $post);
            
            $header = array_keys($data);
            $xls->addRow($header);
            $xls->addRow($data);
        }
        
        $company = $this->getApiGuest()->system_company();
        $title = strtolower($company['name'].'_'.$gateway.'_'.$year . '_'. $month);
        $title = str_replace(',', '_', $title);
        $xls->generateXML($title);
        exit;
    }
}