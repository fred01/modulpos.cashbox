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
        $order = $event->getParameter('ENTITY');
        $cashBoxId = CashboxModul::getModulCashboxId();

        $checkRows = CheckManager::getPrintableChecks(array($cashBoxId), array($order->getId()));

        foreach($checkRows as $checkRow) {
            $check = CheckManager::create($checkRow);
            CashboxModul::enqueCheck($check);
        }
    }

}
?>