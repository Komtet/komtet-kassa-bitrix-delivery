<?php

IncludeModuleLangFile(__FILE__);

return array(
    'parent_menu' => 'global_menu_services',
    'section' => 'komtet.delivery',
    'sort' => 500,
    'text' => GetMessage('KOMTETDELIVERY_MENU_TEXT'),
    'title' => GetMessage('KOMTETDELIVERY_MENU_TEXT'),
    'icon' => 'currency_menu_icon',
    'page_icon' => 'currency_page_icon',
    'items_id' => 'menu_komtet_delivery',
    'items' => array(
        array(
            'text' => GetMessage('KOMTETDELIVERY_MENU_REPORTS_TEXT'),
            'title' => GetMessage('KOMTETDELIVERY_MENU_REPORTS_TEXT'),
            'url' => 'komtet_delivery_reports.php?lang=' . LANGUAGE_ID
        )
    )
);
