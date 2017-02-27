<?php
use Bitrix\Main;
use Modulpos\Cashbox\CashboxModul;

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

defined('MODULE_NAME') or define('MODULE_NAME', 'modulpos.cashbox');

if (!CModule::IncludeModule(MODULE_NAME))
	return;

$request = Main\Application::getInstance()->getContext()->getRequest();

$accessDenied = TRUE;
$token = $request->get('token');
if ($token)
{
    $associationData = CashboxModul::getAssociationData();
    $validToken = base64_encode($associationData['username'].'$'.$associationData['password']);    

	$token = trim($token);
    $accessDenied = $token == $validToken;
}

if ($accessDenied)
{
	CHTTP::SetStatus("403 Forbidden");
	$APPLICATION->FinalActions();
	die();
}

$APPLICATION->RestartBuffer();
header('Content-Type: application/json');

$result = array()
$result['']

echo Main\Web\Json::encode($result);

$APPLICATION->FinalActions();




?>