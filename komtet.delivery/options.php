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
    'DIV' => $moduleId.'-options',
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
        'delivery_status' => 'string'
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
echo bitrix_sessid_post();
$form->EndEpilogContent();

$form->Begin(array('FORM_ACTION' => '/bitrix/admin/settings.php?'.$queryData));

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
        'size' => 50,
        'maxlength' => 255
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

if(CModule::IncludeModule("sale"))
{
    $orderStatuses = StatusLangTable::getList(
        array(
          'select' => array('*'),
          'filter' => array('STATUS.TYPE'=>'O'),
          'select' => array('STATUS_ID', 'NAME')
        )
    );

    $deliveryStatuses = StatusLangTable::getList(
        array(
          'select' => array('*'),
          'filter' => array('STATUS.TYPE'=>'D'),
          'select' => array('STATUS_ID', 'NAME')
        )
    );

    while($orderStatus = $orderStatuses->Fetch())
    {
        $orderList[$orderStatus["STATUS_ID"]] = $orderStatus["NAME"];
    }
    while($deliveryStatus = $deliveryStatuses->Fetch())
    {
        $deliveryList[$deliveryStatus["STATUS_ID"]] = $deliveryStatus["NAME"];
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
        $deliveryList,
        COption::GetOptionString($moduleId, 'delivery_status')
    );

}

if (COption::GetOptionString($moduleId, 'shop_id') and
    COption::GetOptionString($moduleId, 'secret_key'))
{
  $client = new Client(COption::GetOptionString($moduleId, 'shop_id'),
                       COption::GetOptionString($moduleId, 'secret_key'));

  $courierManager = new CourierManager($client);
  try{
    $couriers = array_map(function($courier){
      return array('id' => $courier['id'],
                   'name' => $courier['name']);
    }, $courierManager->getCouriers()['couriers']);
  }
  catch (Exception $e){
    error_log(sprintf('Ошибка получения списка доступных курьеров. Exception: %s', $e));
  }

  if ($couriers){
    function AddCourierDropDownField($form, $id, $content, $required, $arSelect, $value=false, $arParams=array())
    {
      if($value === false)
            $value = $form->arFieldValues[$id];

        $html = '<select name="'.$id.'"';
        foreach($arParams as $param)
            $html .= ' '.$param;
        $html .= '>';

        $html .= '<option value="0"'.($value === 0 ? ' selected': '').'>'."Не выбрано".'</option>';
        foreach($arSelect as $key => $val)
            $html .= '<option value="'.htmlspecialcharsbx($val['id']).'"'.($value == $val['id']? ' selected': '').'>'.htmlspecialcharsex($val['name']).'</option>';
        $html .= '</select>';

        $form->tabs[$form->tabIndex]["FIELDS"][$id] = array(
            "id" => $id,
            "required" => $required,
            "content" => $content,
            "html" => '<td width="40%">'.($required? '<span class="adm-required-field">'.$form->GetCustomLabelHTML($id, $content).'</span>': $form->GetCustomLabelHTML($id, $content)).'</td><td>'.$html.'</td>',
            "hidden" => '<input type="hidden" name="'.$id.'" value="'.htmlspecialcharsbx($value).'">',
        );
    }

    AddCourierDropDownField(
      $form,
      'DEFAULT_COURIER',
      GetMessage('KOMTETDELIVERY_OPTIONS_DEFAULT_COURIER'),
      true,
      $couriers,
      COption::GetOptionString($moduleId, 'default_courier')
    );
  }
}


$form->Buttons(array(
    'disabled' => false,
    'back_url' => (empty($back_url) ? 'settings.php?lang=' . LANG : $back_url)
));

$form->Show();

$form->End();
