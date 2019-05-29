<?if(!check_bitrix_sessid()) return;?>
<?
    if(CModule::IncludeModule("sale"))
    {
        $groupName = "КОМТЕТ Касса Доставка";

        $personTypeList = CSalePersonType::GetList(array(),
                                                   array(),
                                                   false,
                                                   false,
                                                   array());

        while ($personType = $personTypeList->Fetch())
        {
          $groupID = CSaleOrderPropsGroup::Add(array(
                                                  "PERSON_TYPE_ID" => $personType["ID"],
                                                  "NAME" => $groupName,
                                                  "SORT"=> "100",
                                              ));

          $arFields = array(
                "ADDRESS" => array(
                                    "PERSON_TYPE_ID" => $personType["ID"],
                                    "NAME"=> "Адрес доставки",
                                    "TYPE"=> "TEXT",
                                    "REQUIED"=> "Y" ,
                                    "SORT"=> "100" ,
                                    "PROPS_GROUP_ID"=> $groupID,
                                    "CODE"=> "kkd_address"),
                "TIME_START" => array(
                                    "PERSON_TYPE_ID" => $personType["ID"],
                                    "NAME"=> "Время доставки от",
                                    "TYPE"=> "TEXT",
                                    "REQUIED"=> "Y" ,
                                    "SORT"=> "100" ,
                                    "PROPS_GROUP_ID"=> $groupID,
                                    "CODE"=> "kkd_address"),
                "TIME_FINISH" => array(
                                    "PERSON_TYPE_ID" => $personType["ID"],
                                    "NAME"=> "Время доставки до",
                                    "TYPE"=> "TEXT",
                                    "REQUIED"=> "Y" ,
                                    "SORT"=> "100" ,
                                    "PROPS_GROUP_ID"=> $groupID,
                                    "CODE"=> "kkd_address"),
                "DATE" => array(
                                    "PERSON_TYPE_ID" => $personType["ID"],
                                    "NAME"=> "Время доставки до",
                                    "TYPE"=> "TEXT",
                                    "REQUIED"=> "Y" ,
                                    "SORT"=> "100" ,
                                    "PROPS_GROUP_ID"=> $groupID,
                                    "CODE"=> "kkd_address"),
            );
          foreach ($arFields as $arField) {
              CSaleOrderProps::Add($arField);
          }

        }
    }
?>
    <form action="<?echo $APPLICATION->GetCurPage()?>">
  		<input type="hidden" name="lang" value="<?echo LANGUAGE_ID; ?>">
  		<input type="submit" name="" value="<?echo GetMessage("MOD_BACK")?>">
  	<form>
