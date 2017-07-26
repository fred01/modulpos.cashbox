<?php

namespace Modulpos\Cashbox;

use Bitrix\Main;
use Bitrix\Main\Config\Option;
use Bitrix\Sale\Cashbox\Cashbox;
use Bitrix\Sale\Cashbox\Check;
use Bitrix\Sale\Cashbox\Internals\CashboxTable;
use Bitrix\Main\Localization\Loc;

defined('MODULE_CASHBOX_NAME') or define('MODULE_CASHBOX_NAME', 'modulpos.cashbox');

Loc::loadMessages(__FILE__);



class CashboxModul extends Cashbox {	

	public static function log($log_entry, $log_file = null) {
	    if ($log_file == null) {
            $log_file = $_SERVER["DOCUMENT_ROOT"].BX_PERSONAL_ROOT.'/tmp/modulpos.logs/modulpos.cashbox.log';
            CheckDirPath($_SERVER["DOCUMENT_ROOT"].BX_PERSONAL_ROOT.'/tmp/modulpos.logs/');
        }

		file_put_contents($log_file, "\n".date('Y-m-d H:i:sP').' : '.$log_entry, FILE_APPEND);
	}
	
	public static function getModulCashboxId() {
		$id = null;

        $data = CashboxTable::getRow(
                        array(
                            'select' => array('ID'),
                            'filter' => array('=HANDLER' => '\Modulpos\Cashbox\CashboxModul')
                        ));

        if (is_array($data) && $data['ID'] > 0) {
            $id = $data['ID'];
        }
		return $id;
	}
		
    private static function sendHttpRequest($url, $method, $auth_data, $fn_base_url, $data = '') {
        $encoded_auth =  base64_encode($auth_data['username'].':'.$auth_data['password']);
        static::log("sendHttpRequest called:".$url.','.$method.','.$encoded_auth);
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic '.$encoded_auth
        );
        if ($method == 'POST' && $data != '') {
            $headers['Content-Length'] = mb_strlen($data, '8bit');
        }
        $headers_string = '';
        foreach ($headers as $key => $value) {
            $headers_string .= $key.': '.$value."\r\n";
        }
        $options = array(
            'http' => array(
                'header' => $headers_string,
                'method' => $method                
            ),
            'https' => array(
                'header' => $headers_string,
                'method' => $method                
            )
        );
        if ($method == 'POST' && $data != '') {
            $options['http']['content'] = $data;
        }
        $context  = stream_context_create($options);
        static::log("Request: ".$method.' '.$fn_base_url.$url."\n$headers_string\n".$data);
        $response = file_get_contents($fn_base_url.$url, false, $context);
        if ($response === false) { 
            static::log("Error:".var_export(error_get_last(), true));
            return false;
        }
        static::log("\nResponse:\n".var_export($response, true));
        return json_decode($response, true);
    }

    public static function isAssociated() {
        return Option::get(MODULE_CASHBOX_NAME, 'associated_login', '#empty#') !== '#empty#';
    }

    public static function createAssociation($retailpoint_id, $login, $password, $operating_mode) {
        $fn_base_url = static::getFnBaseUrlByMode($operating_mode);
        $response = static::sendHttpRequest('/v1/associate/'.$retailpoint_id, 'POST', array('username'=>$login, 'password' => $password ), $fn_base_url);
        if ($response !== false) {
            $associated_login = $response['userName'];
            $associated_password = $response['password'];
            $operating_mode = $response['operating_mode'];
            $retail_point_info = '';
            if ($response['name']) {
                $retail_point_info .= $response['name'];
            }
            if ($response['address']) {
                $retail_point_info .= ' '.$response['address'];
            }

            $isCp1251 = (SITE_CHARSET == 'windows-1251');
            if ($isCp1251) {
                $retail_point_info = mb_convert_encoding($retail_point_info, "windows-1251", "utf-8");
            }


            Option::set(MODULE_CASHBOX_NAME, 'associated_login', $associated_login);
            Option::set(MODULE_CASHBOX_NAME, 'associated_password', $associated_password);
            Option::set(MODULE_CASHBOX_NAME, 'retail_point_info', $retail_point_info);
            Option::set(MODULE_CASHBOX_NAME, 'operating_mode', $operating_mode);
            return array(
                'success' => TRUE,
                'data' => array(
                    'associated_login' => $associated_login,
                    'associated_password' => $associated_password
                )
            );
        } else {
            return array(
                'success' => FALSE,
                'error' => error_get_last()['message']
            );
        }
    }

    public static function removeCurrentAssociation() {
        $credentials = static::getAssociationData();
        if ($credentials !== FALSE) {
            $fn_base_url = static::getFnBaseUrl();
            $response = static::sendHttpRequest('/associate', 'DELETE', $credentials, $fn_base_url);
            if ($response === FALSE) {
                // Actually doesn't matter'
                static::log('Error deleting association:'.var_export(error_get_last(), TRUE));
            }
        } else {
            static::log('ERROR: CashboxModul module not configured. Can not remove association');
        }
        
    }

    private static function getFnBaseUrl() {
        $operating_mode =  Option::get(MODULE_CASHBOX_NAME, 'operating_mode', 'production');
        return static::getFnBaseUrlByMode($operating_mode);
    }

    private static function getFnBaseUrlByMode($operating_mode) {
        if ($operating_mode == 'demo') {
            return 'https://demo-fn.avanpos.com/fn';
        } else {
            return 'https://service.modulpos.ru/api/fn';
        }
    }

    public static function getAssociationData() {
        $associated_login =  Option::get(MODULE_CASHBOX_NAME, 'associated_login', '#empty#');
        $associated_password = Option::get(MODULE_CASHBOX_NAME, 'associated_password', '');
        if ($associated_login == '#empty#') {
            return false;
        } else {
            return array(
                'username' => $associated_login,
                'password' => $associated_password
            );
        }
    }

    public static function getName() {
        return Loc::getMessage('CASHBOX_MODULPOS_NAME');
    }

    /**
     * @param \Bitrix\Sale\Cashbox\SellCheck $check
     */
    public static function enqueCheck($check) {
        static::log('enqueueCheck called!'.var_export($check->getDataForCheck(), TRUE));
        $document = static::createDocuemntByCheck($check->getDataForCheck());
        static::log('Modulpos document: '.var_export($document, TRUE));
        $document_as_json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        static::log('Modulpos document (JSON):'.var_export($document_as_json, TRUE));
        $credentials = static::getAssociationData();
        if ($credentials !== FALSE) {
            $fn_base_url = static::getFnBaseUrl();
            $response = static::sendHttpRequest('/v1/doc', 'POST', $credentials, $fn_base_url, $document_as_json);
            if ($response === FALSE) {
                // Just log for now, check will be retrieved in next time by FN Service
                static::log('Error enqueuing check:'.var_export(error_get_last(), TRUE));            
            }
        } else {
            static::log('ERROR: CashboxModul module not configured. Print checks on this cashbox is disabled');
        }
    }

	private static function createDocuemntByCheck($checkData) {
        $docId = $checkData['unique_id'];

        $customerContact = $checkData['client_email'];
        if (!$customerContact) {
            $customerContact = $checkData['client_phone'];
        }
        if (!$customerContact) {
            $customerContact = '';
        }

		$document = array(
		           'id' => $checkData['unique_id'],
		           'checkoutDateTime' => $checkData['date_create']->format(DATE_ATOM),
		           'docNum' => $docId,
		           'docType' => $checkData['type'] == 'sell' || $checkData['type'] == 'sellorder' ? 'SALE' : 'RETURN',
		           'email' => $customerContact,
                   'responseURL' => static::getConnectionLink('cashboxmodul_print_callback.php', $docId).'&docId='.$docId
		        );
		
		$inventPositions = array();

		if(Option::get(MODULE_CASHBOX_NAME, "default_do_not_send_invent_positions", false)) {
            $inventPositions[] = static::createFakeItemPosition($checkData['items']);
        } else {
            foreach ($checkData['items'] as $item) {
                $inventPositions[] = static::createItemPositionByCheckItem($item);
            }
        }
        $document['inventPositions'] = $inventPositions;
		
		$moneyPositions = array();
		foreach ($checkData['payments'] as $paymentItem) {
			$moneyPositions[] = static::createMoneyPositionByCheckPayment($paymentItem);
		}
		$document['moneyPositions'] = $moneyPositions;
		
		return $document;
	}

	private static function createItemPositionByCheckItem($item) {
        $itemName = $item['name'];
        if (SITE_CHARSET == 'windows-1251') {
            $itemName = mb_convert_encoding($itemName, "utf-8", "windows-1251");
        }

        $vatTag = Option::get(MODULE_CASHBOX_NAME, "default_vat_tag", '1105');

		$position = array(
		           'description' => '',
		           'name' => $itemName,
		           'price' => static::createPriceByCheckItem($item),
		           'quantity' => $item['quantity'],
		           'vatTag' => intval($vatTag),
                   'discSum' => 0
		        );
		return $position;
	}

    private static function createPriceByCheckItem($item) {
        $base_price = $item['base_price'];
        if($base_price == null ) {
            $discount = static::createDiscountByCheckItem($item);
            return doubleval($item['price']) - $discount;
        } else {
            return $item['price'];
        }

    }

    private static function createDiscountByCheckItem($item) {
        if (array_key_exists('discount', $item) && array_key_exists('discount', $item['discount']) ) {
            $discount = $item['discount']['discount'];
            if($discount != null ) {
                return doubleval($discount);
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }
	
	private static function createMoneyPositionByCheckPayment($paymentItem) {
		$position = array(
            'paymentType' => ($paymentItem['is_cash'] == 'Y' ? 'CASH' : 'CARD'), 
            'sum' => $paymentItem['sum']
        );
        return $position;
    }

    /* TODO Make cacheable with invalidation on options save */
    private static function createValidationToken($document_number) {
        $associationData = static::getAssociationData();
        return md5($associationData['username'].'$'.$associationData['password'].'$'.$document_number);
    }

    public static function validateToken($token, $document_number) {
        if (!$token) {
            return FALSE;
        }
        return trim($token) == static::createValidationToken($document_number);
    }

    public static function getConnectionLink($handler_file, $document_number = "")  {
        $context = Main\Application::getInstance()->getContext();
        $scheme = $context->getRequest()->isHttps() ? 'https' : 'http';
        $server = $context->getServer();
        $domain = $server->getServerName();

        if (preg_match('/^(?<domain>.+):(?<port>\d+)$/', $domain, $matches))
        {
                $domain = $matches['domain'];
                $port   = $matches['port'];
        }
        else
        {
                $port = $server->getServerPort();
        }
        $port = in_array($port, array(80, 443)) ? '' : ':'.$port;
        $token = static::createValidationToken($document_number);
        return sprintf('%s://%s%s/bitrix/tools/%s?token=%s', $scheme, $domain, $port, $handler_file, $token);
    }

    /**
     * Метод подставляет фиктивное значение номенклатуры товаров,
     * согласно п. 17 ст. 7 Закона № 290-ФЗ
     *
     * @see http://www.consultant.ru/document/cons_doc_LAW_200743/6a73a7e61adc45fc3dd224c0e7194a1392c8b071/
     *
     * @param $items
     *
     * @return array
     */
    private static function createFakeItemPosition($items)
    {
        $itemName = Loc::getMessage('CASHBOX_FAKE_PRODUCT_NAME');
        if (SITE_CHARSET == 'windows-1251') {
            $itemName = mb_convert_encoding($itemName, "utf-8", "windows-1251");
        }

        $vatTag = Option::get(MODULE_CASHBOX_NAME, "default_vat_tag", '1105');

        $totalSum = 0;
        foreach ($items as $item){
            $totalSum = $totalSum + static::createPriceByCheckItem($item);
        }

        return array(
            'description' => '',
            'name' => $itemName,
            'price' => $totalSum,
            'quantity' => 1,
            'vatTag' => intval($vatTag),
            'discSum' => 0
        );
    }

    public function buildCheckQuery(Check $check) {
        return array();
    }
    
    public function buildZReportQuery($id) {
        return array();
    }    

	public function getCheckLink(array $linkParams)
	{
		if (isset($linkParams['qr']) && !empty($linkParams['qr']))
		{
			$ofd = $this->getOfd();
			if ($ofd !== null)
				return $ofd->generateCheckLink($linkParams['qr']);
		}
		return '';
	}
}
?>