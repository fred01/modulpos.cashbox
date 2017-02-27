<?php

namespace Modulpos\Cashbox;

use \Modulpos\Cashbox\CashboxModul;
use \Bitrix\Main\Event;
use \Bitrix\Main\EventResult;
use \Bitrix\Sale\Cashbox\CheckManager;

class EventHandler {
    public static function OnGetCashboxHandler(\Bitrix\Main\Event $event) {
        $handlerList = array(            
            '\Modulpos\Cashbox\CashboxModul' => '/bitrix/modules/modulpos.cashbox/lib/cashboxmodul.php'
        );        
        return new EventResult(EventResult::SUCCESS, $handlerList);
    }

    public static function OnSaveOrder(\Bitrix\Main\Event $event) {
        CashboxModul::log('OnSaveOrder called');
        $order = $event->getParameter('ENTITY');
        $cashBoxId = CashboxModul::getModulCashboxId();

        $checkRows = CheckManager::getPrintableChecks(array($cashBoxId), array($order->getId()));

        foreach($checkRows as $checkRow) {
            CashboxModul::log('Check row = '.var_export($checkRow, TRUE));
            $check = CheckManager::create($checkRow);
            CashboxModul::log('Check  = '.var_export($check, TRUE));
            CashboxModul::enqueCheck($check);
        }
    }

}
?>