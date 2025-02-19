<?php

require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php";

IncludeModuleLangFile(__FILE__);

$APPLICATION->SetTitle(GetMessage('KOMTETDELIVERY_REPORTS_TITLE'));

if (!CModule::IncludeModule("komtet.delivery")) {
    ShowError(GetMessage('KOMTETDELIVERY_REPORTS_MODULE_INCLUDE_ERROR'));
    require $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php";
}

$list = new CAdminList('komtetdelivery_reports');
$list->addHeaders(array(
    array(
        "id" => 'id',
        "content" => GetMessage('KOMTETDELIVERY_REPORTS_ITEM_ID'),
        "default" => true,
    ),
    array(
        "id" => 'order_id',
        "content" => GetMessage('KOMTETDELIVERY_REPORTS_ITEM_ORDER_ID'),
        "default" => true,
    ),
    array(
        "id" => 'kk_id',
        "content" => GetMessage('KOMTETDELIVERY_REPORTS_ITEM_KK_ID'),
        "default" => true,
    ),
    array(
        "id" => 'request',
        "content" => GetMessage('KOMTETDELIVERY_REPORTS_REQUEST'),
        "default" => true,
    ),
    array(
        "id" => 'response',
        "content" => GetMessage('KOMTETDELIVERY_REPORTS_RESPONSE'),
        "default" => true,
    ),
));


$page = filter_input(INPUT_GET, 'PAGEN_1', FILTER_VALIDATE_INT);
$page = $page ? $page : 1;
$limit = 10;
$offset = abs(intval($page * $limit - $limit));
$totalItems = (int)KomtetDeliveryReportsTable::getCount();
$navData = new CDBResult();
$navData->NavPageCount = (int)ceil($totalItems / $limit);
$navData->NavPageNomer = $page;
$navData->NavNum = 1;
$navData->NavPageSize = $limit;
$navData->NavRecordCount = $totalItems;

$items = KomtetDeliveryReportsTable::getList(array(
    'order' => array('id' => 'DESC'),
    'offset' => $offset,
    'limit' => $limit
));

while ($item = $items->fetch()) {
    $request = json_decode($item['request'], true);
    $response = json_decode($item['response'], true);

    $request = json_encode($request, JSON_UNESCAPED_UNICODE);
    $response = json_encode($response, JSON_UNESCAPED_UNICODE);

    $list->AddRow('rowid', array(
        'id' => $item['id'],
        'order_id' => $item['order_id'],
        'kk_id' => $item['kk_id'],
        'request' => $request,
        'response' => $response,
    ));
}

$list->DisplayList();

$APPLICATION->IncludeComponent('bitrix:system.pagenavigation', '', array('NAV_RESULT' => $navData));

require $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php";
