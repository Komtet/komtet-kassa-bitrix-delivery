<?php

use Bitrix\Sale\Order as OrderTable;
use Komtet\KassaSdk\Exception\ApiValidationException;
use Komtet\KassaSdk\Exception\ClientException;
use Komtet\KassaSdk\Client;
use Komtet\KassaSdk\EmployeeManager;
use Komtet\KassaSdk\EmployeeType;
use Komtet\KassaSdk\Order;
use Komtet\KassaSdk\OrderManager;
use Komtet\KassaSdk\OrderPosition;
use Komtet\KassaSdk\Payment;
use Komtet\KassaSdk\TaxSystem;
use Komtet\KassaSdk\Vat;

const MEASURE_NAME = 'шт';
const PAYSTATUS = 'Y';

const SHOP_ID_LENGTH = 6;
const SECRET_KEY_LENGTH = 10;


class KomtetDelivery
{
    public static function handleSalePayOrder($order)
    {
        $orderId = $order->getFieldValues()['ORDER_ID'];
        $ok = new KomtetDeliveryD7();
        $ok->createOrder($orderId);
    }

    public static function doneOrder($orderId)
    {
        $ok = new KomtetDeliveryD7();
        $ok->doneOrder($orderId);
    }
}

class KomtetDeliveryCouriers
{
    protected $moduleID = 'komtet.delivery';

    public function getCourierList()
    {
        $shop_id = COption::GetOptionString($this->moduleID, 'shop_id');
        $secret_key = COption::GetOptionString($this->moduleID, 'secret_key');

        if ($shop_id && $secret_key && strlen($shop_id) == SHOP_ID_LENGTH && strlen($secret_key) == SECRET_KEY_LENGTH) {
            $client = new Client($shop_id, $secret_key);

            $employeeManager = new EmployeeManager($client);
            try {
                return $employeeManager->getEmployees('0', '100', EmployeeType::COURIER)['account_employees'];
            } catch (Exception $e) {
                error_log(sprintf('Ошибка получения списка доступных курьеров. Exception: %s', $e));
            }
        }
    }

    public function updateList()
    {
        $courier_list = $this->getCourierList();

        $default_courier = COption::GetOptionString($this->moduleID, 'default_courier');

        //группы свойств
        $groups = CSaleOrderPropsGroup::GetList(
            array(),
            array('NAME' => GetMessage('MOD_GROUP_NAME'))
        );
        // для каждой группы получаем свойство "Курьер"
        while ($propsGroup = $groups->Fetch()) {
            $fields = CSaleOrderProps::GetList(
                array(),
                array(
                    'PROPS_GROUP_ID' => $propsGroup['ID'],
                    'NAME' => GetMessage('PROPERTY_COURIER'),
                )
            );
            // для каждого свойства устанавливаем курьеров
            while ($field = $fields->Fetch()) {
                // чистим поля и дефолтное значение
                CSaleOrderPropsVariant::DeleteAll($field['ID']);
                CSaleOrderProps::Update($field['ID'], array('DEFAULT_VALUE' => ''));

                // добавляем курьеров в список
                foreach ($courier_list as $courier) {
                    CSaleOrderPropsVariant::Add(
                        array(
                            'ORDER_PROPS_ID' => $field['ID'],
                            'NAME' => mb_convert_encoding($courier['name'], LANG_CHARSET, 'UTF-8'),
                            'VALUE' => $courier['id'],
                        )
                    );
                }
                // устанавливаем дефолтного курьера
                if ($default_courier) {
                    CSaleOrderProps::Update(
                        $field['ID'],
                        array('DEFAULT_VALUE' => $default_courier)
                    );
                }
            }
        }
    }
}

class KomtetDeliveryD7
{
    protected $manager;
    protected $shouldForm;
    protected $taxSystem;

    public function __construct()
    {
        $options = $this->getOptions();

        if (!$this->optionsValidate($options)) {
            return false;
        }

        $client = new Client($options['key'], $options['secret']);

        $this->manager = new OrderManager($client);
        $this->shouldForm = $options['should_form'];
        $this->taxSystem = $options['tax_system'];

        $this->modGroupName = mb_convert_encoding('КОМТЕТ Касса Доставка', LANG_CHARSET, 'WINDOWS-1251');
        $this->orderStatus = $options['order_status'];
        $this->deliveryStatus = $options['delivery_status'];
        $this->deliveryTypes = $options['delivery_types'];
        $this->payStatus = PAYSTATUS;
    }

    public function getoptions()
    {
        $moduleID = 'komtet.delivery';
        $result = array(
            'key' => COption::GetOptionString($moduleID, 'shop_id'),
            'secret' => COption::GetOptionString($moduleID, 'secret_key'),
            'should_form' => COption::GetOptionInt($moduleID, 'should_form') == 1,
            'tax_system' => intval(COption::GetOptionInt($moduleID, 'tax_system')),
            'order_status' => COption::GetOptionString($moduleID, 'order_status'),
            'delivery_status' => COption::GetOptionString($moduleID, 'delivery_status'),
            'delivery_types' => json_decode(COption::GetOptionString($moduleID, 'delivery_types')),
        );

        return $result;
    }

    protected function getPaymentType($payment)
    {
        $paySystem = $payment->getPaySystem();
        return $paySystem->isCash() ? Payment::TYPE_CASH : Payment::TYPE_CARD;
    }

    protected function getUserInfo($user)
    {
        return array(
            'FIO' => sprintf('%s %s %s', $user['NAME'], $user['SECOND_NAME'], $user['LAST_NAME']),
            'EMAIL' => $user['EMAIL'],
            'PHONE' => $user['PERSONAL_PHONE']
        );
    }

    public function createOrder($orderId)
    {
        // проверяем, включен ли плагин
        if (!$this->shouldForm) {
            error_log(sprintf('[Order - %s] Заказ не создан, флаг генерации не установлен', $orderId));

            return false;
        }

        // проверяем, статус отгрузки
        $order = OrderTable::load($orderId);
        if (!$order->isAllowDelivery()) {
            return false;
        }

        $kOrderID = KomtetDeliveryReportsTable::getRow(array(
            'select' => array('*'),
            'filter' => array('order_id' => $orderId),
        ));

        if (!$kOrderID) {
            $kOrderID = KomtetDeliveryReportsTable::add(['order_id' => $orderId])->getId();
        } else {
            $kOrderID = $kOrderID['id'];
        }

        $customFields = CSaleOrderPropsValue::GetOrderProps($orderId);
        while ($customField = $customFields->Fetch()) {
            if ($customField['GROUP_NAME'] === $this->modGroupName) {
                $customFieldList[$customField['CODE']] = mb_convert_encoding($customField['VALUE'], 'UTF-8', LANG_CHARSET);
            }
        }

        $shipmentCollection = $order->getShipmentCollection();

        if (!$this->validation($kOrderID, $customFieldList, $shipmentCollection)) {
            return false;
        }

        $paymentCollection = $order->getPaymentCollection();
        $orderDelivery = new Order(
            $order->getId(),
            'new',
            $this->taxSystem,
            $order->isPaid(),
            0,
            $this->getPaymentType($paymentCollection[0])
        );

        $userInfo = $this->getUserInfo(CUser::GetByID($order->getUserId())->Fetch());
        $orderDelivery->setClient(
            $customFieldList['kkd_address'],
            $userInfo['PHONE'],
            $userInfo['EMAIL'],
            $userInfo['FIO']
        );

        $positions = $order->getBasket();
        foreach ($positions as $position) {

            $positionID = $position->getField('ID');

            if ($position->getField('MARKING_CODE_GROUP')) {
                // Если позиция с маркировкой, то разбиваем позицию на единицы
                $nomenclature_codes = $this->getNomenclatureCodes($positionID);

                for ($item = 0; $item < $position->getQuantity(); $item++) {
                    $order_position = $this->generatePosition($position);
                    $nomenclature_code = array_shift($nomenclature_codes);
                    $order_position->setNomenclatureCode($nomenclature_code);
                    $orderDelivery->addPosition($order_position);
                }
            } else {
                // Если нет маркировок, то проводим как обычную позицию
                $orderDelivery->addPosition($this->generatePosition($position, $position->getQuantity()));
            }
        }


        foreach ($shipmentCollection as $shipment) {
            if ($shipment->getPrice() > 0.0) {
                $shipmentVatRate = Vat::RATE_NO;
                if ($this->taxSystem === TaxSystem::COMMON and method_exists($shipment, 'getVatRate')) {
                    $shipmentVatRate = strval(floatval($shipment->getVatRate()) * 100);
                }

                $orderDelivery->addPosition(new OrderPosition([
                    'oid' => $shipment->getDeliveryId(),
                    'name' => mb_convert_encoding($shipment->getField('DELIVERY_NAME'), 'UTF-8', LANG_CHARSET),
                    'price' => round($shipment->getPrice(), 2),
                    'quantity' => 1,
                    'total' => round($shipment->getPrice(), 2),
                    'vat' => $shipmentVatRate,
                    'measure_name' => mb_convert_encoding(MEASURE_NAME, 'UTF-8', LANG_CHARSET),
                ]));
            }
        }

        if ($customFieldList['kkd_courier']) {
            $orderDelivery->setCourierId(intval($customFieldList['kkd_courier']));
        }
        if ($order->getField('USER_DESCRIPTION')) {
            $orderDelivery->setDescription(mb_convert_encoding($order->getField('USER_DESCRIPTION'), 'UTF-8', LANG_CHARSET));
        }

        $scheme = array_key_exists('HTTPS', $_SERVER) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
        $url = sprintf('%s://%s/%s/%s/', $scheme, $_SERVER['SERVER_NAME'], 'done_order', $orderId);
        $orderDelivery->setCallbackUrl(mb_convert_encoding($url, 'UTF-8', LANG_CHARSET));

        $normalDate = implode('-', array_reverse(explode('.', $customFieldList['kkd_date'])));
        $startDate = sprintf('%s %s', $normalDate, $customFieldList['kkd_time_start']);
        $endDate = sprintf('%s %s', $normalDate, $customFieldList['kkd_time_end']);
        $orderDelivery->setDeliveryTime($startDate, $endDate);

        $kkd_order = KomtetDeliveryReportsTable::getByID($kOrderID)->Fetch();
        try {
            if (is_null($kkd_order['kk_id']) || $kkd_order['kk_id'] == 0) {
                $response = $this->manager->createOrder($orderDelivery);
            } else {
                $response = $this->manager->updateOrder($kkd_order['kk_id'], $orderDelivery);
            }
        } catch (ApiValidationException $e) {
            $response = array(
                "Title" => $e->getTitle(),
                "Code" => $e->getVLDCode(),
                "Description" => $e->getDescription()
            );
        } catch (ClientException $e) {
            $response = array("Title" => $e->getMessage());
        } finally {
            Logger::print_log(
                $kOrderID,
                array(
                    'request' => json_encode($orderDelivery->asArray()),
                    'response' => json_encode($response),
                    'kk_id' => (!is_null($kkd_order['kk_id']) && $kkd_order['kk_id'] != 0) ? $kkd_order['kk_id'] : $response['id'],
                )
            );
        }
    }

    private function generatePosition($position, $quantity = 1)
    {
        /**
         * Получение позиции заказа
         * @param array $position Позиция в заказе Bitrix
         * @param int|float $quantity Кол-во товара в позиции
         */

        $itemVatRate = Vat::RATE_NO;
        if ($this->taxSystem == TaxSystem::COMMON) {
            $itemVatRate = strval(floatval($position->getField('VAT_RATE')) * 100);
        }

        return new OrderPosition([
            'oid' => $position->getField('ID'),
            'name' => mb_convert_encoding($position->getField('NAME'), 'UTF-8', LANG_CHARSET),
            'price' => round($position->getPrice(), 2),
            'quantity' =>  $quantity,
            'total' => round($position->getFinalPrice(), 2),
            'vat' => $itemVatRate,
            'measure_name' => mb_convert_encoding($position->getField('MEASURE_NAME'), 'UTF-8', LANG_CHARSET),
        ]);
    }

    public function getNomenclatureCodes($position_id)
    {
        /**
         * Получение списка маркировок
         * @param int $position_id Идентификатор позиции в заказе
         */
        global $DB;

        $strSql = "SELECT MARKING_CODE FROM b_sale_store_barcode WHERE BASKET_ID = " . $position_id;
        $dbRes = $DB->Query($strSql, false);

        $nomenclature_codes = [];
        while ($nomenclature_code = $dbRes->Fetch()) {
            array_push($nomenclature_codes, $nomenclature_code['MARKING_CODE']);
        }
        return $nomenclature_codes;
    }

    public function doneOrder($orderId)
    {
        if (!CModule::IncludeModule('sale')) {
            return false;
        }

        if (CModule::IncludeModule('komtet.kassa')) {
            $ID = KomtetKassaReportsTable::getRow(array(
                'select' => array('*'),
                'filter' => array('order_id' => $orderId),
            ));

            if (!$ID) {
                KomtetKassaReportsTable::add([
                    'order_id' => $orderId,
                    'state' => 0,
                ]);
            }
        }

        $order = OrderTable::load($orderId);

        $shipments = $order->getShipmentCollection();
        foreach ($shipments as $shipment) {
            if (!$shipment->isSystem()) {
                $shipment->setField('STATUS_ID', $this->deliveryStatus);
            }
        }
        $order->save();

        CSaleOrder::PayOrder($orderId, $this->payStatus);
        CSaleOrder::StatusOrder($orderId, $this->orderStatus);
    }

    private function validation($kOrderID, $customFieldList, $shipmentCollection)
    {
        if (!$this->customFieldsValidate($customFieldList, $kOrderID)) {
            return false;
        }

        if (!$this->shipmentValidate($shipmentCollection, $kOrderID)) {
            return false;
        }

        return true;
    }

    private function customFieldsValidate($customFieldList, $kOrderID)
    {
        foreach (array('kkd_date', 'kkd_time_start', 'kkd_time_end', 'kkd_address') as $key) {
            if (empty($customFieldList[$key])) {
                error_log(sprintf('Дополнительное поле "%s" для модуля "komtet.delivery" не установлено', $key));
                Logger::print_log($kOrderID, array('request' => sprintf('Ошибка заполнения поля "%s"', $key)));

                return false;
            }
        }

        return true;
    }

    private function optionsValidate($options)
    {
        foreach (array('key', 'secret', 'tax_system') as $key) {
            if (empty($options[$key])) {
                error_log(sprintf('Настройка "%s" для модуля "komtet.delivery" не найдена', $key));

                return false;
            }

            return true;
        }
    }

    private function shipmentValidate($shipmentCollection, $kOrderID)
    {
        foreach ($shipmentCollection as $shipment) {
            if (in_array($shipment->getDeliveryId(), $this->deliveryTypes)) {
                return true;
            }
        }
        error_log(sprintf('Выбранный тип доставки не установлен в настройках'));
        Logger::print_log($kOrderID, array('request' => sprintf('Выбранный тип доставки не установлен в настройках')));

        return false;
    }
}

class Logger
{
    public static function print_log($kOrderID, $message)
    {
        foreach ($message as $key => $value) {
            $message[$key] = mb_convert_encoding($value, LANG_CHARSET, 'WINDOWS-1251');
        }

        KomtetDeliveryReportsTable::update($kOrderID, $message);
    }
    public static function dump($kOrderID, $data)
    {
        $logName = "/bitrix/modules/komtet_delivery.log";
        $f = fopen($_SERVER['DOCUMENT_ROOT'] . $logName, 'ab');
        fwrite($f, "OrderID: " . $kOrderID . "\n==\n");
        fwrite($f, print_r($data, 1) . "\n==\n");
        fclose($f);
    }
}
