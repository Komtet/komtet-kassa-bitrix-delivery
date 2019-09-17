<?php

use Bitrix\Main\UserTable;
use Bitrix\Sale\Order as OrderTable;
use Komtet\KassaSdk\Exception\SdkException;
use Komtet\KassaSdk\Client;
use Komtet\KassaSdk\Order;
use Komtet\KassaSdk\OrderManager;
use Komtet\KassaSdk\OrderPosition;
use Komtet\KassaSdk\Payment;
use Komtet\KassaSdk\TaxSystem;
use Komtet\KassaSdk\Vat;

const MEASURE_NAME = '��';
const PAYSTATUS = 'Y';

class KomtetDelivery
{
    public static function handleSalePayOrder($order)
    {
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
            error_log("������ ��������� ��������");
            return false;
        }

        $client = new Client($options['key'], $options['secret']);

        $this->manager = new OrderManager($client);
        $this->shouldForm = $options['should_form'];
        $this->taxSystem = $options['tax_system'];
        $this->defaultCourier = $options['default_courier'];

        $this->modGroupName = "������ ����� ��������";
        $this->orderStatus = $options['order_status'];
        $this->deliveryStatus = $options['delivery_status'];
        $this->deliveryType = $options['delivery_type'];
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
            'default_courier' => intval(COption::GetOptionInt($moduleID, 'default_courier')),
            'order_status' => COption::GetOptionString($moduleID, 'order_status'),
            'delivery_status' => COption::GetOptionString($moduleID, 'delivery_status'),
            'delivery_type' => COption::GetOptionString($moduleID, 'delivery_type'),
        );

        return $result;
    }

    public function createOrder($orderId)
    {   
        $kOrderID = KomtetDeliveryReportsTable::getRow(array(
            'select' => array('*'),
            'filter' => array('order_id' => $orderId)
        ));

        if (!$kOrderID) {
            $kOrderID = KomtetDeliveryReportsTable::add(['order_id' => $orderId])->getId();
        } else {
            $kOrderID = $kOrderID['id'];
        }

        if (!$this->shouldForm) {
            error_log(sprintf('[Order - %s] ����� �� ������, ���� ��������� �� ����������', $orderId));
            return false;
        }

        $order = OrderTable::load($orderId);
        if (!$order->isAllowDelivery()) {
            return false;
        }

        $customFields = CSaleOrderPropsValue::GetOrderProps($orderId);
        while ($customField = $customFields->Fetch()) {
            if ($customField['GROUP_NAME'] === $this->modGroupName) {
                $customFieldList[$customField["CODE"]] = $customField["VALUE"];
            }
        }

        $userId = $order->getUserId();
        $rsUser = UserTable::getById($userId)->fetch();

        $shipmentCollection = $order->getShipmentCollection();

        if (!$this->validation($orderId, $customFieldList, $rsUser, $shipmentCollection)) {
            KomtetDeliveryReportsTable::update(
                $kOrderID,
                array("request" => 'validation error')
            );
            return false;
        }

        $orderDelivery = new Order(
            $order->getId(),
            'new',
            $this->taxSystem,
            $order->isPaid(),
            0,
            Payment::TYPE_CARD
        );
        $orderDelivery->setClient(
            $customFieldList['kkd_address'],
            $rsUser['PERSONAL_PHONE'],
            $rsUser['EMAIL'],
            sprintf(
                "%s %s %s",
                $rsUser['NAME'],
                $rsUser['SECOND_NAME'],
                $rsUser['LAST_NAME']
            )
        );

        $positions = $order->getBasket();
        foreach ($positions as $position) {
            $itemVatRate = Vat::RATE_NO;
            if ($this->taxSystem == TaxSystem::COMMON) {
                $itemVatRate = strval(floatval($position->getField('VAT_RATE'))*100);
            }

            $orderDelivery->addPosition(new OrderPosition([
                'oid' => $position->getField('ID'),
                'name' => $position->getField('NAME'),
                'price' => round($position->getPrice(), 2),
                'quantity' => $position->getQuantity(),
                'total' => round($position->getFinalPrice(), 2),
                'vat' => $itemVatRate,
                'measure_name' => $position->getField('MEASURE_NAME'),
            ]));
        }

        foreach ($shipmentCollection as $shipment) {
            if ($shipment->getPrice() > 0.0 and $shipment->getDeliveryId() === $this->deliveryType) {

                $shipmentVatRate = Vat::RATE_NO;
                if ($this->taxSystem === TaxSystem::COMMON and method_exists($shipment, 'getVatRate')) {
                    $shipmentVatRate = strval(floatval($shipment->getVatRate())*100);
                }

                $orderDelivery->addPosition(new OrderPosition([
                    'oid' => $shipment->getDeliveryId(),
                    'name' => mb_convert_encoding($shipment->getField('DELIVERY_NAME'), 'UTF-8', LANG_CHARSET),
                    'price' => round($shipment->getPrice(), 2),
                    'quantity' => 1,
                    'total' => round($shipment->getPrice(), 2),
                    'vat' => $shipmentVatRate,
                    'measure_name' => MEASURE_NAME,
                ]));
            }
        }

        if (!$this->defaultCourier == 0) {
            $orderDelivery->setCourierId($this->defaultCourier);
        }
        if ($order->getField('USER_DESCRIPTION')) {
            $orderDelivery->setDescription($order->getField('USER_DESCRIPTION'));
        }

        $scheme = array_key_exists('HTTPS', $_SERVER) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
        $url = sprintf('%s://%s/%s/%s/', $scheme, $_SERVER['SERVER_NAME'], "done_order", $orderId);
        $orderDelivery->setCallbackUrl($url);

        $normalDate = implode("-", array_reverse(explode(".", $customFieldList["kkd_date"])));
        $startDate = sprintf("%s %s", $normalDate, $customFieldList["kkd_time_start"]);
        $endDate = sprintf("%s %s", $normalDate, $customFieldList["kkd_time_end"]);
        $orderDelivery->setDeliveryTime($startDate, $endDate);

        $kkd_order = KomtetDeliveryReportsTable::getByID($kOrderID)->Fetch();
        try {
            if (is_null($kkd_order['kk_id'])) {
                $response = $this->manager->createOrder($orderDelivery);
            } else {
                $response = $this->manager->updateOrder($kkd_order['kk_id'], $orderDelivery);
            }
        } catch (SdkException $e) {
            error_log(sprintf('������ �������� ������: %s', $e->getMessage()));
        } finally {
            KomtetDeliveryReportsTable::Update(
                $kOrderID,
                array(
                    "request" => json_encode($orderDelivery->asArray()),
                    "response" => json_encode($response),
                    "kk_id" => !is_null($kkd_order['kk_id']) ? $kkd_order['kk_id'] : $response['id']
                )
            );
        }
    }

    public function doneOrder($orderId)
    {
        if (!CModule::IncludeModule("sale")) {
            return false;
        }

        if (CModule::IncludeModule("komtet.kassa")) {
            $ID = KomtetKassaReportsTable::getRow(array(
                'select' => array('*'),
                'filter' => array('order_id' => $orderId)
            ));

            if (!$ID) {
                KomtetKassaReportsTable::add([
                    'order_id' => $orderId,
                    'state' => 0
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

    private function validation($orderId, $customFieldList, $rsUser, $shipmentCollection)
    {
        if (!$this->customFieldsValidate($customFieldList)) {
            error_log(sprintf('[Order - %s] ������ ���������� �������������� �����', $orderId));
            return false;
        }

        if (!$this->userValidate($rsUser)) {
            error_log(sprintf('[Order - %s] ������ ��������� ������������', $orderId));
            return false;
        }

        if (!$this->shipmentValidate($shipmentCollection)) {
            error_log(sprintf('[Order - %s] ������ ������ ������� ��������', $orderId));
            return false;
        }

        return true;
    }

    private function customFieldsValidate($customFieldList)
    {
        foreach (array('kkd_address', 'kkd_date', 'kkd_time_start', 'kkd_time_end') as $key) {
            if (empty($customFieldList[$key])) {
                error_log(sprintf('�������������� ���� "%s" ��� ������ "komtet.delivery" �� �����������', $key));
                return false;
            }
        }
        return true;
    }

    private function optionsValidate($options)
    {
        foreach (array('key', 'secret', 'tax_system') as $key) {
            if (empty($options[$key])) {
                error_log(sprintf('��������� "%s" ��� ������ "komtet.delivery" �� �������', $key));
                return false;
            }
            return true;
        }
    }

    private function userValidate($user)
    {
        if (empty($user['PERSONAL_PHONE'])) {
            error_log(sprintf('� ������������ "%s" �� ������ ����� ��������', $user['ID']));
            return false;
        }
        return true;
    }

    private function shipmentValidate($shipmentCollection)
    {
        foreach ($shipmentCollection as $shipment) {
            if ($shipment->getDeliveryId() === $this->deliveryType) {
                return true;
            }
        }
        error_log(sprintf('��������� ��� �������� �� ���������� � ����������'));
        return false;
    }
}
