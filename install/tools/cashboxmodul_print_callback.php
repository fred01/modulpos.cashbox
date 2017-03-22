<?php
ini_set('display_errors', '0');

use Bitrix\Main;
use Bitrix\Sale\Cashbox\Internals\CashboxCheckTable;
use Modulpos\Cashbox\CashboxModul;

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if (!CModule::IncludeModule("sale")) {
	return;
}

defined('MODULE_NAME') or define('MODULE_NAME', 'modulpos.cashbox');

if (!CModule::IncludeModule(MODULE_NAME))
	return;

$request = Main\Application::getInstance()->getContext()->getRequest();

if (!CashboxModul::validateToken($request->get('token'), $request->get('docId')))
{
	CHTTP::SetStatus("403 Forbidden");
	$APPLICATION->FinalActions();
	die();
}

$printedDocId = $request->get('docId');
$qrCode = $request->get('qr');

$dbRes = CashboxCheckTable::getList(array('select' => array('STATUS'), 'filter' => array('ID' => $printedDocId)));
$data = $dbRes->fetch();
if ($data)
{
    if ($data['STATUS'] !== 'Y')
    {
        $result = CashboxCheckTable::update(
            $printedDocId,
            array(
                'STATUS' => 'Y',
                'LINK_PARAMS' => array('qr' => $qrCode),
                'DATE_PRINT_END' => new Main\Type\DateTime()
            )
        );
    }
}

print "Completed";