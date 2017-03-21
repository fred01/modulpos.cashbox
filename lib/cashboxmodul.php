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
	const FN_BASE_URL = 'http://demo-fn.avanpos.com/fn';
    const CACHE_ID = 'MODULPOS_CASHBOX_ID';
    const CACHE_EXPIRE_TIME = 31536000;
		
	public static function log($log_entry, $log_file="/var/log/php/modulpos.cashbox.log") {
		// Uncomment line bellow to enable debuging of module
		// file_put_contents($log_file, "\n".date('Y-m-d H:i:sP').' : '.$log_entry, FILE_APPEND);
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
		
    private static function sendHttpRequest($url, $method, $auth_data, $data = '') {
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
            )
        );
        if ($method == 'POST' && $data != '') {
            $options['http']['content'] = $data;
        }
        $context  = stream_context_create($options);
        static::log("Request: ".$method.' '.static::FN_BASE_URL.$url."\n$headers_string\n".$data);
        $response = file_get_contents(static::FN_BASE_URL.$url, false, $context);
        if ($response === false) { 
            static::log("Error:".var_export(error_get_last(), true));
            return false;
        }
        static::log("\nResponse:\n".var_export($response, true));
        return json_decode($response, true);
    }
    private static function getAssociationData() {
        $associated_login =  Option::get(MODULE_NAME, 'associated_login', '#empty#');
        if ($associated_login == '#empty#') {
            $login =  Option::get(MODULE_NAME, 'login', '#empty#');
            if ($login != '#empty#') {
                $password =  Option::get(MODULE_NAME, 'password', '');
                $retailpoint_id = Option::get(MODULE_NAME, 'retailpoint_id', '');
                $response = static::sendHttpRequest('/v1/associate/'.$retailpoint_id, 'POST', array('username'=>$login, 'password' => $password ));
                if ($response !== false) {
                    $associated_login = $response['userName'];
                    $associated_password = $response['password'];
                    Option::set(MODULE_NAME, 'associated_login', $associated_login);
                    Option::set(MODULE_NAME, 'associated_password', $associated_password);
                    return array(
                        'username' => $associated_login,
                        'password' => $associated_password
                    );
                } else {
                    // TODO Show error message about not filled options
                }
            } else {
                // TODO Show error message about not filled options
            }
        } else {
            $associated_password = Option::get(MODULE_NAME, 'associated_password', '');
            static::log("Use stored association data: $associated_login , $associated_password");
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
        $response = static::sendHttpRequest('/v1/doc', 'POST', $credentials, $document_as_json);
        if ($response === FALSE) {
            // Just log for now, check will be retrieved in next time by FN Service
            static::log('Error enqueuing check:'.var_export(error_get_last(), TRUE));            
        }
    }

	private static function createDocuemntByCheck($checkData) {
		$document = array(
		           'id' => $checkData['unique_id'],
		           'checkoutDateTime' => $checkData['date_create']->format(DATE_ATOM),
		           'docNum' => $checkData['unique_id'],
		           'docType' => $checkData['type'] == 'sell' || $checkData['type'] == 'sellorder' ? 'SALE' : 'RETURN',
		           'email' => $checkData['client_email'],
                   'responseURL' => static::getConnectionLink('cashboxmodul_print_callback.php').'&docId='.$checkData['unique_id']
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
		           'price' => $item['price'],
		           'quantity' => $item['quantity'],
		           'vatTag' => $item['vat']
		        );
		return $position;
	}
	
	private static function createMoneyPositionByCheckPayment($paymentItem) {
		$position = array(
            'paymentType' => ($paymentItem['is_cash'] == 'Y' ? 'CASH' : 'CARD'), 
            'sum' => $paymentItem['sum']
        );
        return $position;
    }

    /* TODO Make cacheable with invalidation on options save */
    private static function createValidationToken() {
        $associationData = static::getAssociationData();
        return md5($associationData['username'].'$'.$associationData['password']);    
    }

    public static function validateToken($token) {
        if (!$token) {
            return FALSE;
        }
        return trim($token) == static::createValidationToken();
    }

    public static function getConnectionLink($handler_file)  {
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
        $token = static::createValidationToken();
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