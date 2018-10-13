<?php

if ($_GET['go'] != 'Y') {
    exit;

}

Class SYSTEM
{

    #===============================================================================================
    const TAX_VALUES = array(  // налоговые ставки
        '0_percent' => 0,       //НДС 0%
        '10_percent' => 10,     //НДС 10%
        '18_percent' => 18,     //НДС 18%
        'none' => -1,           // НДС не облагается
        'basic_118' => 118,     // НДС 18/118
        'basic_110' => 110,     // НДС 10/110
    );
    #-----------------------------------------------------------------------------------------------
    const TAX_TYPES = array(  // тип системы налогообложения
        'common' => 0,                      // Общая ОСН
        'simple_revenue' => 1,              // Упрощенная УСН (Доход)
        'simple_profit' => 2,               // Упрощенная УСН (Доход минус Расход)
        'envd' => 3,                        // Единый налог на вмененный доход ЕНВД
        'eshn' => 4,                        // Единый сельскохозяйственный налог ЕСН
        'patent' => 5,                      // Патентная система налогообложения
    );
    #-----------------------------------------------------------------------------------------------
    const OPERATION_TYPES = array( // тип операции
        'sale_rentabox_make' => 0,          // продажа;
        'sale_rentabox_back' => 1,          // возврат продажи;
        'sale__egais__make' => 8,           // продажа только по ЕГАИС (обычный чек ККМ не печатается)  
        'sale__egais__back' => 9,           // возврат продажи только по ЕГАИС (обычный чек ККМ не печатается)
        'purchase_rentabox_make' => 10,     // покупка;
        'purchase_rentabox_back' => 11,     // возврат покупки; 
    );
    #===============================================================================================

    #===============================================================================================
    static $md5_pref = 'jgkjhkjhkhkjh';
    static $md5_secret = 'kljlkjlkjljlkj'; // лучше не менять!!!
    static $cashierName = 'ООО Рентабокс';  // Продавец, тег ОФД 1021
    static $cashierSlogan = "Rentabox.ru - центр хранения вещей";
    static $paymentName = "Аренда бокса (www.rentabox.ru)";
    static $paymentQuantity = 1;
    static $cashierDeptCode = 1;  // Основные услуги
    static $paymentTaxValue = 18;  // ставка 18% по умолчанию
    static $ID_INN = '7724836177';
    static $ID_KKT = '0189650005008212';
    #===============================================================================================

    #===============================================================================================
    static $operationType; // +++ тип операции из списка
    static $operationID;
    static $operationDate;
    static $cashierTaxVariant;  // +++ Система налогообложения (СНО) применяемая для чека (*)
    static $clientID; // +++ ID клиента - необязательно!!!
    static $clientEmail; // +++ Телефон или е-Майл покупателя, тег ОФД 1008 - указывать обязательно
    static $paymentPrice;
    // *) Если не указанно - система СНО настроенная в ККМ по умолчанию
    #===============================================================================================

    #===============================================================================================
    static function update($taxation_type_id, $oper_type_id, $client_id, $email, $price, $kkm_doc_id, $tax_code = NULL)
    {
        $tv = SYSTEM::TAX_VALUES;
        SYSTEM::$cashierTaxVariant = SYSTEM::TAX_TYPES[$taxation_type_id];
        # ...........................
        SYSTEM::$paymentPrice = $price;
        SYSTEM::$clientID = $client_id;
        if ($tax_code && isset($tv[$tax_code])) {
            SYSTEM::$paymentTaxValue == $tv[$tax_code];
        }
    }
    #===============================================================================================

}

Class KKMConnect
{

    #===============================================================================================
    var $swShowErr = FALSE;
    #-----------------------------------------------------------------------------------------------
    var $authUser = '';
    var $authPass = "";
    #-----------------------------------------------------------------------------------------------
    var $reqMethod = 'POST';
    var $reqRemoteIP = '';
    # ...........................


    var $kass_hash_salt = '';
    #===============================================================================================

    #===============================================================================================
    const REQUEST_MAKE_PAYMENT = array(
        'Command' => 'RegisterCheck',
        'NumDevice' => 0,
        'InnKkm' => '7701237658', // +++
        'KktNumber' => '0149060506089651', // +++
        'Timeout' => 15,
        'IdCommand' => '', // +++
        'IsFiscalCheck' => TRUE,
        # ...........................
        'CheckProps' => '',
        'ClientId' => '', // +++
        'KPP' => '772401001',
        'CheckStrings' => array(
            array(
                'PrintText' => array(
                    'Text' => '', // +++
                    'Font' => 4,
                    'Intensity' => '15',
                ),
            ),
            array(
                'Register' => array(
                    'Name' => '', // +++
                    'Quantity' => '', // +++
                    'Price' => '', // +++
                    'Amount' => '', // +++
                    'Department' => '', // +++
                    'Tax' => '', // +++
                ),
            ),
        ),
        'Cash' => 0,
        'CashLessType1' => '', // +++
        'CashLessType2' => 0,
        'CashLessType3' => 0,
    );
    #-----------------------------------------------------------------------------------------------
    const REQUEST_KKM_LIST = array(
        'Command' => 'List',
        'NumDevice' => 0,
        'InnKkm' => '',
        'Active' => TRUE,
        'OnOff' => '',
        'OFD_Error' => '',
        'OFD_DateErrorDoc' => '',
        'FN_DateEnd' => '',
        'FN_MemOverflowl' => '',
        'FN_IsFiscal' => '',
    );
    # ...........................
    #-----------------------------------------------------------------------------------------------
    function getRequest($params = array())
    {
        $data_json = json_encode($params, JSON_UNESCAPED_UNICODE);
        $i = '';
        foreach ($this->makeHD(strlen($data_json)) as $v) {
            $i .= $v . chr(13) . chr(10);
        }
        return $i . $data_json . chr(13) . chr(10) . chr(13) . chr(10);
    }

    #-----------------------------------------------------------------------------------------------
    function sendDataBySocket($data = array(), $showSocketErr = NULL)
    {
        $tl = $this->reqExeTL;
        $sock = fsockopen($this->reqRemoteIP, $this->reqRemotePort, $errNumber, $errDesc, $tl);
        if (!$sock && $showSocketErr) {
            echo 'Error: ' . $errDesc . ' #' . $errNumber;
            exit;
        }
        fwrite($sock, $this->getRequest($data));
        $hd = $dt = '';
        $sw = 0;
        $ts = $this->reqExeStart;
        while (!feof($sock) && (microtime(true) - $ts) < $tl) {
            if ($sw) {
                $dt .= fread($sock, 4096);
            } else {
                $r = fgets($sock, 4096);
                if ($r === chr(13) . chr(10)) {
                    $sw = 1;
                } else {
                    $hd .= $r;
                }
            }
        }
        fclose($sock);
        return array($hd, (array)json_decode($dt));
    }
    #===============================================================================================

    #===============================================================================================
    function get_kkm_list()
    {
        return $this->sendDataBySocket(self::REQUEST_KKM_LIST, 1);
    }

    #-----------------------------------------------------------------------------------------------
    function get_kkm_status($n)
    {
        $data = self::REQUEST_KKM_STATUS;
        $data['NumDevice'] = $n;
        $data['IdCommand'] = SYSTEM::$operationID = SYSTEM::$md5_pref . md5(time() . SYSTEM::$md5_secret);
        return $this->sendDataBySocket($data, 1);
    }
    #-----------------------------------------------------------------------------------------------
...............
}

#===================================================================================================
$kkm = new KKMConnect(1);
#---------------------------------------------------------------------------------------------------
if ($_GET['oper'] == 'get_kkm_list') {
    list($header, $answer) = $kkm->get_kkm_list();
} #---------------------------------------------------------------------------------------------------
elseif ($_GET['oper'] == 'get_kkm_status') {
    list($header, $answer) = $kkm->get_kkm_status(1);
} // 1 - основной ККТ
#---------------------------------------------------------------------------------------------------
elseif ($_GET['oper'] == 'new_kkt_session') {
    list($header, $answer) = $kkm->new_kkt_session(1);
} // 1 - основной ККТ
#---------------------------------------------------------------------------------------------------
elseif ($_GET['oper'] == 'close_kkt_session') {
    list($header, $answer) = $kkm->close_kkt_session(1);
} // 1 - основной ККТ
#---------------------------------------------------------------------------------------------------
elseif ($kkm->chOperPayment()) {
    $kkm->makePayment('common', 'sale_rentabox_make',
        $_GET['login'], $_GET['email'], $_GET['amount'], $_GET['command']);
}
#---------------------------------------------------------------------------------------------------
.....................

exit;