<?php
IncludeModuleLangFile(__FILE__);

class komtet_delivery extends CModule
{
    var $MODULE_ID = 'komtet.delivery';
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $GROUP_NAME;
    private $INSTALL_DIR;

    public function __construct()
    {
        $this->MODULE_ID = "komtet.delivery";
        $this->MODULE_NAME = GetMessage('KOMTETDELIVERY_MODULE_NAME');
        $this->MODULE_DESCRIPTION = GetMessage('KOMTETDELIVERY_MODULE_DESCRIPTION');
        $this->PARTNER_NAME = GetMessage('KOMTETDELIVERY_PARTNER_NAME');
        $this->PARTNER_URI = "https://kassa.komtet.ru";
        $this->INSTALL_DIR = dirname(__file__);
        $this->GROUP_NAME = GetMessage('MOD_GROUP_NAME');
        $arModuleVersion = array();
        include(realpath(sprintf('%s/version.php', $this->INSTALL_DIR)));
        if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion["VERSION"];
            $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        }

        $this->FILES = array(
            "admin" => array(
                "FROM" => sprintf('%s/%s', $this->INSTALL_DIR, "admin"),
                "TO" => sprintf('%s/bitrix/%s', $_SERVER["DOCUMENT_ROOT"], 'admin')
            ),
            "tools" => array(
                "FROM" => sprintf('%s/%s', $this->INSTALL_DIR, "tools"),
                "TO" => sprintf('%s/%s', $_SERVER["DOCUMENT_ROOT"], 'komtet.delivery')
            )
        );
    }

    public function DoInstall()
    {
        global $APPLICATION;
        if (!IsModuleInstalled("sale")) {
            echo(CAdminMessage::ShowMessage(Array("TYPE"=>"ERROR",
                                                  "MESSAGE" =>GetMessage("MOD_INST_ERR"),
                                                  "DETAILS"=>GetMessage("MOD_ERR_SALE_NOT_FOUND"),
                                                  "HTML"=>true)));
            return false;
        }
        if (!IsModuleInstalled($this->MODULE_ID))
        {
            if (!$this->DoInstallDB() or !$this->DoInstallFields()){
                if($ex = $APPLICATION->GetException()) {
                    echo(CAdminMessage::ShowMessage(Array("TYPE"=>"ERROR",
                                                          "MESSAGE" =>GetMessage("MOD_INST_ERR"),
                                                          "DETAILS"=>$ex->GetString(),
                                                          "HTML"=>true)));
                }
                return false;
            }

            $this->DoInstallFiles();
            COption::SetOptionString($this->MODULE_ID, 'server_url', 'https://kassa.komtet.ru');
            COption::SetOptionInt($this->MODULE_ID, 'should_form', 1);
            RegisterModule($this->MODULE_ID);
            $saleModuleInfo = CModule::CreateModuleObject('sale');
            RegisterModuleDependences('sale', 'OnShipmentAllowDelivery', $this->MODULE_ID, 'KomtetDelivery', 'handleSalePayOrder');

            $callback_url = array(
                'CONDITION' => '#^/done_order/([0-9a-zA-Z-]+)/#',
                'RULE' => 'ORDER_ID=$1',
                'ID' => 'bitrix:komtet.delivery',
                'PATH' => sprintf('/%s/%s', 'komtet.delivery', 'komtet_delivery_done_order.php'),
                'SORT' => 100);

            CUrlRewriter::Add($callback_url);

        }
        else {
          return false;
        }
    }

    public function DoInstallFiles()
    {
        foreach ($this->FILES as $file) {
            CopyDirFiles(
                $file["FROM"],
                $file["TO"],
                true,
                true
            );
        }
    }

    public function DoUninstall()
    {
        if (IsModuleInstalled($this->MODULE_ID))
        {
            $this->DoUninstallFields();
            COption::RemoveOption($this->MODULE_ID);

            UnRegisterModule($this->MODULE_ID);
            UnRegisterModuleDependences("sale", "OnShipmentAllowDelivery", $this->MODULE_ID, "KomtetDelivery", "handleSalePayOrder");
            $this->DoUninstallDB();
            $this->DoUninstallFiles();
            CUrlRewriter::Delete(array('ID' => 'bitrix:komtet.delivery'));
        }
    }

    public function DoUninstallFiles()
    {
        foreach ($this->FILES as $file) {
            DeleteDirFiles(
                $file["FROM"],
                $file["TO"]
            );
        }
        rmdir($this->FILES["tools"]["TO"]);
    }

    public function DoInstallDB()
    {
        global $DB, $DBType, $APPLICATION;
        $errors = $DB->RunSQLBatch(sprintf('%s/db/%s/install.sql', $this->INSTALL_DIR, $DBType));
        if (empty($errors)){
          return true;
        }
        $APPLICATION->ThrowException(implode('', $errors));
        return false;
    }

    public function DoUninstallDB()
    {
        global $DB, $DBType;
        $DB->RunSQLBatch(sprintf('%s/db/%s/uninstall.sql', $this->INSTALL_DIR, $DBType));
    }

    public function DoInstallFields()
    {
        global $APPLICATION;
        if(CModule::IncludeModule("sale"))
        {
            $new_status = array(
                    'ID' => 'KD',
                    'SORT' => 100,
                    'TYPE' => 'D',
                    'COLOR' => '#00FF00',
                    'LANG' => array(
                    array(
                      "LID"=>'ru',
                      "NAME"=>"Доставлен",
                      "DESCRIPTION"=>"Статус выставляется приложением КОМТЕТ Касса Курьер"),
                    array(
                      "LID"=>'en',
                      "NAME"=>"Delivered"))
            );

            $arStatus = CSaleStatus::GetByID($new_status['ID']);
            if (!$arStatus) {
                    CSaleStatus::Add($new_status);
            }

            $personTypeList = CSalePersonType::GetList(array(),
                                                       array(),
                                                       false,
                                                       false,
                                                       array());

            if (intval($personTypeList->SelectedRowsCount()) === 0) {
                $APPLICATION->ThrowException(GetMessage("MOD_ERR_PERSON_NOT_FOUND"));
                return false;
            }

            while ($personType = $personTypeList->Fetch())
            {
                $groupID = CSaleOrderPropsGroup::Add(array(
                                                        "PERSON_TYPE_ID" => $personType["ID"],
                                                        "NAME" => $this->GROUP_NAME,
                                                        "SORT"=> "100",
                                                    ));

                $arFields = array(
                      "ADDRESS" => array(
                                          "PERSON_TYPE_ID" => $personType["ID"],
                                          "NAME" => "Адрес доставки",
                                          "TYPE" => "TEXT",
                                          "REQUIED" => "Y" ,
                                          "SORT" => "100" ,
                                          "PROPS_GROUP_ID" => $groupID,
                                          "CODE" => "kkd_address"),
                      "DATE" => array(
                                          "PERSON_TYPE_ID" => $personType["ID"],
                                          "NAME" => "Дата доставки",
                                          "TYPE" => "DATE",
                                          "REQUIED" => "Y" ,
                                          "SORT" => "100" ,
                                          "PROPS_GROUP_ID" => $groupID,
                                          "CODE" => "kkd_date"),
                      "TIME_START" => array(
                                          "PERSON_TYPE_ID" => $personType["ID"],
                                          "NAME" => "Время доставки от",
                                          "TYPE" => "TEXT",
                                          "REQUIED" => "Y" ,
                                          "SORT" => "100" ,
                                          "PROPS_GROUP_ID" => $groupID,
                                          "CODE" => "kkd_time_start",
                                          "DEFAULT_VALUE" => "00:00",
                                          "SETTINGS"=> array(
                                              "MINLENGTH" => "5",
                                              "MAXLENGTH" => "5",
                                              "PATTERN" => "([01]?[0-9]|2[0-3]):[0-5][0-9]"
                                          )),
                      "TIME_FINISH" => array(
                                          "PERSON_TYPE_ID" => $personType["ID"],
                                          "NAME" => "Время доставки до",
                                          "TYPE" => "TEXT",
                                          "REQUIED" => "Y" ,
                                          "SORT" => "100" ,
                                          "PROPS_GROUP_ID" => $groupID,
                                          "CODE" => "kkd_time_end",
                                          "DEFAULT_VALUE" => "23:00",
                                          "SETTINGS" =>array(
                                              "MINLENGTH" =>"5",
                                              "MAXLENGTH" =>"5",
                                              "PATTERN" => "([01]?[0-9]|2[0-3]):[0-5][0-9]"
                                          )),
                  );
                  foreach ($arFields as $arField) {
                      CSaleOrderProps::Add($arField);
                  }
            }
            return true;
        }
    }

    public function DoUninstallFields()
    {
        if(CModule::IncludeModule("sale"))
  			{
            $groupList= CSaleOrderPropsGroup::GetList(array(),
                                                      array("NAME" => $this->GROUP_NAME),
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
    }

}
