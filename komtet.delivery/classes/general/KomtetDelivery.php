<?php

use Komtet\KassaSdk\Exception\SdkException;
use Komtet\KassaSdk\Client;
use Komtet\KassaSdk\TaxSystem;
use Komtet\KassaSdk\Vat;
use Bitrix\Main\UserTable;


class KomtetDelivery
{
    public static function handleSalePayOrder($id, $val)
    {
        if (gettype($id) == 'object') {
            return;
        }

        if ($val == 'N') {
            return;
        }

        if (!CModule::IncludeModule('sale')) {
            return;
        }
    }

    public static function newHandleSalePayOrder($order)
    {
        if (!gettype($order) == 'object')
        {
            return;
        }

        if (!$order->isPaid()) {
            return;
        }
    }

    public static function newHandleSaleSaveOrder($order)
    {
        if (!gettype($order) == 'object')
        {
            return;
        }

        if (!$order->isPaid()) {
            return;
        }
    }
}

class KomtetDeliveryBase
{
    protected $manager;
}
