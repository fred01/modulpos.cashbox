<?php

namespace Bitrix\Sale\Cashbox;

use Bitrix\Main\Localization;
use Bitrix\Main;

Localization\Loc::loadMessages(__FILE__);

class OfdRu extends Ofd
{
	const ACTIVE_URL = 'https://ofd.ru/rec/';
	const TEST_URL = 'https://ofd.ru/rec/';

	/**
	 * @param string $data
	 * @return string
	 */
	public function generateCheckLink($data)
    {
        $url = static::ACTIVE_URL;
        parse_str($data, $qr);

        return $url.'7810498732'.'/'.'0000321027056631'.'/'.$qr['fn'].'/'.$qr['i'].'/'.$qr['fp'];
    }

	/**
	 * @throws Main\NotImplementedException
	 * @return string
	 */
	public static function getName()
	{
		return 'OFD.RU';
	}
}