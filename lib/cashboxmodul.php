<?php

namespace Modulpos\Cashbox;

use Bitrix\Main;
use Bitrix\Main\Config\Option;
use Bitrix\Sale\Cashbox\Cashbox;
use Bitrix\Sale\Cashbox\Check;
use Bitrix\Sale\Cashbox\Internals\CashboxTable;
use Bitrix\Main\Localization\Loc;

defined('MODULE_NAME') or define('MODULE_NAME', 'modulpos.cashbox');

Loc::loadMessages(__FILE__);



class CashboxModul extends Cashbox {	
//	const FN_BASE_URL = 'https://demo-fn.avanpos.com/fn';
	const FN_BASE_URL = 'https://service.modulpos.ru/api/fn';
    const CACHE_ID = 'MODULPOS_CASHBOX_ID';
    const CACHE_EXPIRE_TIME = 31536000;
		
	public static function log($log_entry, $log_file="/var/log/php/modulpos.cashbox.log") {
		// Uncomment line bellow to enable debuging of module
		file_put_contents($log_file, "\n".date('Y-m-d H:i:sP').' : '.$log_entry, FILE_APPEND);
	}
	
	public static function getModulCashboxId() {

		$id = 0;
		$cacheManager = Main\Application::getInstance()->getManagedCache();
		
		if ($cacheManager->read(CACHE_EXPIRE_TIME, CACHE_ID)) {
			$id = $cacheManager->get(CACHE_ID);
		}
		
		if ($id <= 0) {
			$data = CashboxTable::getRow(
			                array(
			                    'select' => array('ID'),
			                    'filter' => array('=HANDLER' => '\Modulpos\Cashbox\CashboxModul')
			                ));

			if (is_array($data) && $data['ID'] > 0) {
				$id = $data['ID'];
				$cacheManager->set($CACHE_ID, $id);
			}
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
        return Option::get(MODULE_NAME, 'associated_login', '#empty#') !== '#empty#';
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
             
            Option::set(MODULE_NAME, 'associated_login', $associated_login);
            Option::set(MODULE_NAME, 'associated_password', $associated_password);
            Option::set(MODULE_NAME, 'retail_point_info', $retail_point_info);
            Option::set(MODULE_NAME, 'operating_mode', $operating_mode);
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
            $fn_base_url = getFnBaseUrl();
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
        $operating_mode =  Option::get(MODULE_NAME, 'operating_mode', 'demo');
        return getFnBaseUrlByMode($operating_mode);
    }

    private static function getFnBaseUrlByMode($operating_mode) {
        if ($operating_mode == 'production') {
            return 'https://service.modulpos.ru/api/fn';
        } else {
            return 'https://demo-fn.avanpos.com/fn';
        }
    }

    private static function getAssociationData() {
        $associated_login =  Option::get(MODULE_NAME, 'associated_login', '#empty#');
        $associated_password = Option::get(MODULE_NAME, 'associated_password', '');
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

    public static function enqueCheck($check) {
        static::log('enqueueCheck called!'.var_export($check->getDataForCheck(), TRUE));
        $document = static::createDocuemntByCheck($check->getDataForCheck());
        static::log('Modulpos document: '.var_export($document, TRUE));
        $document_as_json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        static::log('Modulpos document (JSON):'.var_export($document_as_json, TRUE));
        $credentials = static::getAssociationData();
        if ($credentials !== FALSE) {
            $fn_base_url = getFnBaseUrl();
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
		$document = array(
		           'id' => $checkData['unique_id'],
		           'checkoutDateTime' => $checkData['date_create']->format(DATE_ATOM),
		           'docNum' => $docId,
		           'docType' => $checkData['type'] == 'sell' || $checkData['type'] == 'sellorder' ? 'SALE' : 'RETURN',
		           'email' => $checkData['client_email'],
                   'responseURL' => static::getConnectionLink('cashboxmodul_print_callback.php', $docId).'&docId='.$docId
		        );
		
		$inventPositions = array();
		foreach ($checkData['items'] as $item) {
			$inventPositions[] = static::createItemPositionByCheckItem($item);
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
		$position = array(            
		           'description' => '',
		           'name' => $item['name'],
		           'price' => static::createPriceByCheckItem($item),
		           'quantity' => $item['quantity'],
		           'vatTag' => $item['vat'],
                   'discSum' => static::createDiscountByCheckItem($item)
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
        $discount = $item['discount']['discount'];
        if($discount != null ) {
            return doubleval($discount);
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