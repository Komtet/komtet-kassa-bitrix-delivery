<?php

use Bitrix\Main\UserTable;
use Bitrix\Sale\Order as OrderTable;
use Bitrix\Sale\Payment;
use Komtet\KassaSdk\Exception\SdkException;
use Komtet\KassaSdk\Client;
use Komtet\KassaSdk\Order;
use Komtet\KassaSdk\OrderManager;
use Komtet\KassaSdk\OrderPosition;
use Komtet\KassaSdk\TaxSystem;
use Komtet\KassaSdk\Vat;


class KomtetDelivery
{

    public static function handleSalePayOrder($order)
    {
        if (!gettype($order) == 'object')
        {
            return;
        }

        $orderId = $order->getFieldValues()["ORDER_ID"];
        $ok = new KomtetDeliveryD7();
        $ok->createOrder($orderId);
    }

    public static function doneOrder($orderId)
    {
        $ok = new KomtetDeliveryD7();
        $ok->doneOrder($orderId);
    }
}

class KomtetDeliveryD7
{
    protected $manager;
    protected $shouldForm;
    protected $taxSystem;
    protected $defaultCourier;

    public function __construct()
    {
        $options = $this->getOptions();

        if (!$this->optionsValidate($options)) {
            error_log("Ошибка валидации настроек");
            return false;
        }

        $client = new Client($options['key'], $options['secret']);

        $this->manager = new OrderManager($client);
        $this->shouldForm = $options['should_form'];
        $this->taxSystem = $options['tax_system'];
        $this->defaultCourier = $options['default_courier'];

        $this->modGroupName = "КОМТЕТ Касса Доставка";
        $this->orderStatus = $options['order_status'];
        $this->deliveryStatus = $options['delivery_status'];
        $this->payStatus = 'Y';

    }

    public function getoptions()
    {
        $moduleID = 'komtet.delivery';
        $result = array(
            'key' => COption::GetOptionString($moduleID, 'shop_id'),
            'secret' => COption::GetOptionString($moduleID, 'secret_key'),
            'should_form' => COption::GetOptionInt($moduleID, 'should_form') == 1,
            'tax_system' => intval(COption::GetOptionInt($moduleID, 'tax_system')),
            'default_courier' => intval(COption::GetOptionInt($moduleID, 'default_courier')),
            'order_status' => COption::GetOptionString($moduleID, 'order_status'),
            'delivery_status' => COption::GetOptionString($moduleID, 'delivery_status')
        );

        return $result;
    }

    public function createOrder($orderId)
    {
        if (!$this->shouldForm ) {
            error_log(sprintf('[Order - %s] Заказ не создан, флаг генерации не установлен', $orderId));
            return false;
        }

        $order = OrderTable::load($orderId);
        if (!$order->isAllowDelivery()) {
            return false;
        }

        $customFields = CSaleOrderPropsValue::GetOrderProps($orderId);
        while ($customField = $customFields->Fetch())
        {
            if ($customField['GROUP_NAME'] === $this->modGroupName)
            {
                $customFieldList[$customField["CODE"]] = $customField["VALUE"];
            }
        }

        $userId = $order->getPersonTypeId();
        $rsUser = UserTable::getById($userId)->fetch();

        if (!$this->customFieldsValidate($customFieldList)) {
            error_log(sprintf('[Order - %s] Ошибка заполенния дополнительных полей', $orderId));
            return false;
        }

        if (!$this->userValidate($rsUser)) {
            error_log(sprintf('[Order - %s] Ошибка валидации пользователя', $orderId));
            return false;
        }

        $orderDelivery = new Order($order->getId(), 'new', $this->taxSystem, $order->isPaid());
        $orderDelivery->setClient($customFieldList['kkd_address'],
                                  $rsUser['PERSONAL_PHONE'],
                                  $rsUser['EMAIL'],
                                  sprintf("%s %s %s",
                                          $rsUser['NAME'],
                                          $rsUser['SECOND_NAME'],
                                          $rsUser['LAST_NAME']));

        $positions = $order->getBasket();
        foreach ($positions as $position) {
            if ($this->taxSystem == TaxSystem::COMMON) {
                $itemVatRate = strval(round(floatval($position->getField('VAT_RATE')) * 100, 2));
            } else {
                $itemVatRate = Vat::RATE_NO;
            }

            $orderPosition = new OrderPosition(['oid' => $position->getField('ID'),
                                                'name' => $position->getField('NAME'),
                                                'price' => round($position->getPrice(),2),
                                                'quantity' => $position->getQuantity(),
                                                'total'=> round($position->getFinalPrice(),2),
                                                'vat' => $itemVatRate,
                                                'measure_name' => $position->getField('MEASURE_NAME'),
                                               ]);

            $orderDelivery->addPosition($orderPosition);
        }

        if (!empty($order->getDeliveryIdList()))
        {
            $orderPosition = new OrderPosition(['oid' => $order->getDeliveryIdList()[0],
                                                'name' => "Доставка",
                                                'price' => round($order->getField("PRICE_DELIVERY"),2),
                                                'quantity' => 1,
                                                'total'=> round($order->getField("PRICE_DELIVERY"),2),
                                                'vat' => Vat::RATE_NO,
                                                'measure_name' => "шт",
                                               ]);
            $orderDelivery->addPosition($orderPosition);
        }


        if (!$this->defaultCourier == 0) {
            $orderDelivery->setCourierId($this->defaultCourier);
        }

        if ($order->getField['USER_DESCRIPTION']) {
            $orderDelivery->setDescription($order->getField['USER_DESCRIPTION']);
        }

        $scheme = array_key_exists('HTTPS', $_SERVER) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
        $url = sprintf('%s://%s/%s/%s/', $scheme, $_SERVER['SERVER_NAME'], "delivery/done_order", $orderId);
        $orderDelivery->setCallbackUrl($url);

        $normalDate = implode("-", array_reverse(explode(".", $customFieldList["kkd_date"])));
        $startDate = sprintf("%s %s", $normalDate, $customFieldList["kkd_time_start"]);
        $endDate = sprintf("%s %s", $normalDate, $customFieldList["kkd_time_end"]);
        $orderDelivery->setDeliveryTime($startDate, $endDate);

        try {
            $this->manager->createOrder($orderDelivery);
        } catch (SdkException $e) {
            error_log(sprintf('Failed to send order: %s', $e->getMessage()));
        }
    }

    public function doneOrder($orderId)
    {
        if(CModule::IncludeModule("sale"))
        {
            $order = OrderTable::load($orderId);
            try {
                CSaleOrder::PayOrder($orderId, $this->payStatus);
                CSaleOrder::StatusOrder($orderId, $this->orderStatus);
                $order->setField("ADDITIONAL_INFO", Date(CDatabase::DateFormatToPHP(CLang::GetDateFormat("FULL", LANG))));
            } catch (Exception $e) {
                echo($e->getMessage());
            }
            $shipments = $order->getShipmentCollection();
            foreach ($shipments as $shipment)
            {
                if(!$shipment->isSystem())
                {
                    $shipment->setField('STATUS_ID', $this->deliveryStatus);
                }
            }
            $order->save();
        }
    }

    private function customFieldsValidate($customFieldList) {
      foreach (array('kkd_address', 'kkd_date', 'kkd_time_start', 'kkd_time_end') as $key) {
          if (empty($customFieldList[$key])) {
              error_log(sprintf('Дополнительное поле "%s" для модуля "komtet.delivery" не установлено', $key));
              return false;
          }
      }
      return true;
    }

    private function optionsValidate($options) {
      foreach (array('key', 'secret', 'tax_system') as $key) {
          if (empty($options[$key])) {
              error_log(sprintf('Option "%s" for module "komtet.delivery" is required', $key));
              return false;
          }
        return true;
      }
    }

    private function userValidate($user) {
        if (empty($user['PERSONAL_PHONE'])) {
            error_log(sprintf('У пользователя "%s" не указан номер телефона', $user['ID']));
            return false;
        }
        return true;
    }
}
