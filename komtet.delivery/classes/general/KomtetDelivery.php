<?php

use Bitrix\Main\UserTable;
use Komtet\KassaSdk\Exception\SdkException;
use Komtet\KassaSdk\Client;
use Komtet\KassaSdk\OrderManager;
use Komtet\KassaSdk\TaxSystem;
use Komtet\KassaSdk\Vat;


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
    protected $shouldForm;
    protected $taxSystem;

    public function __constructor()
    {
        $options = $this->getOptions();
        $client = new Client($options['key'], $options['secret']);

        $this->manager = new OrderManager($client);
        $this->shouldForm = $options['should_form'];
        $this->taxSystem = $options['tax_system'];
        $this->defaultCourier = $options['default_courier'];
    }

    public function getoptions()
    {
        $moduleID = 'komtet.delivery';
        $result = array(
            'key' => COption::GetOptionString($moduleID, 'shop_id'),
            'secret' => COption::GetOptionString($moduleID, 'secret_key'),
            'should_form' => COption::GetOptionInt($moduleID, 'should_form') == 1,
            'tax_system' => intval(COption::GetOptionInt($moduleID, 'tax_system')),
            'default_courier' => intval(COption::GetOptionInt($moduleID, 'default_courier'))
        );
    }
}
