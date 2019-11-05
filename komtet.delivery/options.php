<?php
$moduleId = 'komtet.delivery';

use Bitrix\Main\Loader,
    Bitrix\Main\Localization\Loc;
use Komtet\KassaSdk\Client;
use Komtet\KassaSdk\CourierManager;
use Komtet\KassaSdk\TaxSystem;

use Bitrix\Sale\Internals\StatusLangTable;

if (!$USER->IsAdmin()) {
    return;
}


Loader::includeModule($moduleId);
Loader::includeModule('sale');
Loc::loadMessages(__FILE__);

$form = new CAdminForm('tabControl', array(array(
    'DIV' => $moduleId . '-options',
    'TAB' => GetMessage('MAIN_TAB_SET'),
    'TITLE' => GetMessage('MAIN_TAB_TITLE_SET')
)));

if ($REQUEST_METHOD == 'POST' && check_bitrix_sessid()) {
    $data = array(
        'shop_id' => 'string',
        'secret_key' => 'string',
        'should_form' => 'bool',
        'tax_system' => 'integer',
        'default_courier' => 'integer',
        'order_status' => 'string',
        'delivery_status' => 'string',
        'delivery_type' => 'integer',
    );
    foreach ($data as $key => $type) {
        $value = filter_input(INPUT_POST, strtoupper($key));
        if ($type == 'string') {
            COption::SetOptionString($moduleId, $key, $value);
        } else if ($type == 'bool') {
            COption::SetOptionInt($moduleId, $key, $value === null ? 0 : 1);
        } else if ($type == 'integer') {
            COption::SetOptionInt($moduleId, $key, $value);
        }
    }
}
$queryData =  http_build_query(array(
    'lang' => LANGUAGE_ID,
    'mid' => $moduleId
));

$form->BeginEpilogContent();
$form->EndEpilogContent();

$form->Begin(array('FORM_ACTION' => '/bitrix/admin/settings.php?' . $queryData));

$form->BeginNextFormTab();

$form->AddEditField(
    'SHOP_ID',
    GetMessage('KOMTETDELIVERY_OPTIONS_SHOP_ID'),
    true,
    array(
        'size' => 20,
        'maxlength' => 255
    ),
    COption::GetOptionString($moduleId, 'shop_id')
);

$form->AddEditField(
    'SECRET_KEY',
    GetMessage('KOMTETDELIVERY_OPTIONS_SECRET_KEY'),
    true,
    array(
        'size' => 20,
        'maxlength' => 255,
    ),
    COption::GetOptionString($moduleId, 'secret_key')
);

$form->AddCheckBoxField(
    'SHOULD_FORM',
    GetMessage('KOMTETDELIVERY_OPTIONS_SHOULD_FORM'),
    true,
    COption::GetOptionInt($moduleId, 'should_form'),
    COption::GetOptionInt($moduleId, 'should_form') == 1
);

$form->AddDropDownField(
    'TAX_SYSTEM',
    GetMessage('KOMTETDELIVERY_OPTIONS_TAX_SYSTEM'),
    true,
    array(
        TaxSystem::COMMON => GetMessage('KOMTETDELIVERY_OPTIONS_TS_COMMON'),
        TaxSystem::SIMPLIFIED_IN => GetMessage('KOMTETDELIVERY_OPTIONS_TS_SIMPLIFIED_IN'),
        TaxSystem::SIMPLIFIED_IN_OUT => GetMessage('KOMTETDELIVERY_OPTIONS_TS_SIMPLIFIED_IN_OUT'),
        TaxSystem::UTOII => GetMessage('KOMTETDELIVERY_OPTIONS_TS_UTOII'),
        TaxSystem::UST => GetMessage('KOMTETDELIVERY_OPTIONS_TS_UST'),
        TaxSystem::PATENT => GetMessage('KOMTETDELIVERY_OPTIONS_TS_PATENT')
    ),
    COption::GetOptionString($moduleId, 'tax_system')
);

if (CModule::IncludeModule("sale")) {
    $orderStatuses = StatusLangTable::getList(
        array(
            'select' => array('*'),
            'filter' => array('STATUS.TYPE' => 'O'),
            'select' => array('STATUS_ID', 'NAME')
        )
    );

    $deliveryStatuses = StatusLangTable::getList(
        array(
            'select' => array('*'),
            'filter' => array('STATUS.TYPE' => 'D'),
            'select' => array('STATUS_ID', 'NAME')
        )
    );

    $deliveryTypes = CSaleDelivery::GetList(
        array(),
        array(),
        false,
        false,
        array("ID", "NAME")
    );

    while ($orderStatus = $orderStatuses->Fetch()) {
        $orderList[$orderStatus["STATUS_ID"]] = $orderStatus["NAME"];
    }

    while ($deliveryStatus = $deliveryStatuses->Fetch()) {
        $deliveryStatusList[$deliveryStatus["STATUS_ID"]] = $deliveryStatus["NAME"];
    }

    $deliveryTypeList[0] = GetMessage("KOMTETDELIVERY_OPTIONS_DEFAULT_NAME");
    while ($deliveryType = $deliveryTypes->Fetch()) {
        $deliveryTypeList[$deliveryType["ID"]] = $deliveryType["NAME"];
    }

    $form->AddDropDownField(
        'ORDER_STATUS',
        GetMessage('KOMTETDELIVERY_OPTIONS_ORDER_STATUS'),
        true,
        $orderList,
        COption::GetOptionString($moduleId, 'order_status')
    );

    $form->AddDropDownField(
        'DELIVERY_STATUS',
        GetMessage('KOMTETDELIVERY_OPTIONS_DELIVERY_STATUS'),
        true,
        $deliveryStatusList,
        COption::GetOptionString($moduleId, 'delivery_status')
    );

    $form->AddDropDownField(
        'DELIVERY_TYPE',
        GetMessage('KOMTETDELIVERY_OPTIONS_DELIVERY_TYPE'),
        true,
        $deliveryTypeList,
        COption::GetOptionString($moduleId, 'delivery_type')
    );
}

if (
    COption::GetOptionString($moduleId, 'shop_id') &&
    COption::GetOptionString($moduleId, 'secret_key') &&
    strlen(COption::GetOptionString($moduleId, 'shop_id')) >= 2 && 
    strlen(COption::GetOptionString($moduleId, 'secret_key')) >= 2
) {
    $client = new Client(
        COption::GetOptionString($moduleId, 'shop_id'),
        COption::GetOptionString($moduleId, 'secret_key')
    );

    $courierManager = new CourierManager($client);
    try {
        $kk_couriers = $courierManager->getCouriers()['couriers'];
    } catch (Exception $e) {
        error_log(sprintf('������ ��������� ������ ��������� ��������. Exception: %s', $e));
    }

    
    if ($kk_couriers) {
        $couriersList[0] = GetMessage('KOMTETDELIVERY_OPTIONS_DEFAULT_NAME');

        $encoding = (LANG_CHARSET === 'windows-1251') ? 'CP1251' : 'UTF-8';
        foreach ($kk_couriers as $kk_courier) {
            $couriersList[$kk_courier['id']] = iconv('UTF-8', $encoding, $kk_courier['name']);
            
        }

        $form->AddDropDownField(
            'DEFAULT_COURIER',
            GetMessage('KOMTETDELIVERY_OPTIONS_DEFAULT_COURIER'),
            true,
            $couriersList,
            COption::GetOptionString($moduleId, 'default_courier')
        );
    }
}


$form->Buttons(array(
    'disabled ' => false,
    'back_url' => (empty($backurl)  ? ' settings.php?lang=' . LANG : $back_url)
));

$form->Show();

$form->End();
