<?if(!check_bitrix_sessid()) return;?>
<?
    if(CModule::IncludeModule("sale")) {
        $groupName = "КОМТЕТ Касса Доставка";
        $groupList= CSaleOrderPropsGroup::GetList(array(),
                                                  array("NAME" => $groupName),
                                                  false,
                                                  false,
                                                  array());
        while ($group = $groupList->Fetch())
        {
            $propertyList = CSaleOrderProps::GetList(array(),
                                                     array("PROPS_GROUP_ID" => $group["ID"]),
                                                     false,
                                                     false,
                                                     array());
            while ($property = $propertyList->Fetch())
            {
                CSaleOrderProps::Delete($property["ID"]);
            }
            CSaleOrderPropsGroup::Delete($group["ID"]);
        }
    }
?>
    <form action="<?echo $APPLICATION->GetCurPage()?>">
  		<input type="hidden" name="lang" value="<?echo LANGUAGE_ID; ?>">
  		<input type="submit" name="" value="<?echo GetMessage("MOD_BACK")?>">
  	<form>
