<?php

IncludeModuleLangFile(__FILE__);

class komtet_delivery extends CModule
{
    public $MODULE_ID = 'komtet.delivery';
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $GROUP_NAME;
    private $INSTALL_DIR;
    const DEFAULT_VALUES = array(
        'kkd_time_start' => '00:00',
        'kkd_time_end' => '23:00'
    );

    public function __construct()
    {
        $this->MODULE_ID = 'komtet.delivery';
        $this->MODULE_NAME = GetMessage('KOMTETDELIVERY_MODULE_NAME');
        $this->MODULE_DESCRIPTION = GetMessage('KOMTETDELIVERY_MODULE_DESCRIPTION');
        $this->PARTNER_NAME = GetMessage('KOMTETDELIVERY_PARTNER_NAME');
        $this->PARTNER_URI = 'https://kassa.komtet.ru';
        $this->INSTALL_DIR = dirname(__FILE__);
        $this->GROUP_NAME = GetMessage('MOD_GROUP_NAME');
        $arModuleVersion = array();
        include realpath(sprintf('%s/version.php', $this->INSTALL_DIR));
        if (is_array($arModuleVersion) && array_key_exists('VERSION', $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        }

        $this->FILES = array(
            'admin' => array(
                'FROM' => sprintf('%s/%s', $this->INSTALL_DIR, 'admin'),
                'TO' => sprintf('%s/bitrix/%s', $_SERVER['DOCUMENT_ROOT'], 'admin'),
            ),
            'tools' => array(
                'FROM' => sprintf('%s/%s', $this->INSTALL_DIR, 'tools'),
                'TO' => sprintf('%s/%s', $_SERVER['DOCUMENT_ROOT'], 'komtet.delivery'),
            ),
        );
    }

    public function DoInstall()
    {
        global $APPLICATION;

        if (!extension_loaded('curl')) {
            echo CAdminMessage::ShowMessage(array(
                'TYPE' => 'ERROR',
                'MESSAGE' => GetMessage('MOD_INST_ERR'),
                'DETAILS' => GetMessage('MOD_ERR_CURL_NOT_FOUND'),
                'HTML' => true,
            ));

            return false;
        }

        if (!IsModuleInstalled('sale')) {
            echo CAdminMessage::ShowMessage(array(
                'TYPE' => 'ERROR',
                'MESSAGE' => GetMessage('MOD_INST_ERR'),
                'DETAILS' => GetMessage('MOD_ERR_SALE_NOT_FOUND'),
                'HTML' => true,
            ));

            return false;
        }

        if (version_compare(CModule::CreateModuleObject('sale')->MODULE_VERSION, '15.5.0', '<')) {
            echo CAdminMessage::ShowMessage(array(
                'TYPE' => 'ERROR',
                'MESSAGE' => GetMessage('MOD_INST_ERR'),
                'DETAILS' => GetMessage('MOD_ERR_SALE_UPDATE'),
                'HTML' => true,
            ));

            return false;
        }

        if (IsModuleInstalled($this->MODULE_ID)) {
            echo CAdminMessage::ShowMessage(array(
                'TYPE' => 'ERROR',
                'MESSAGE' => GetMessage('MOD_INST_ERR'),
                'DETAILS' => GetMessage('MOD_ERR_DELIVERY_IS_INSTALLED'),
                'HTML' => true,
            ));

            return false;
        }

        if (!$this->DoInstallDB() or !$this->DoInstallFields()) {
            if ($ex = $APPLICATION->GetException()) {
                echo CAdminMessage::ShowMessage(array(
                    'TYPE' => 'ERROR',
                    'MESSAGE' => GetMessage('MOD_INST_ERR'),
                    'DETAILS' => $ex->GetString(),
                    'HTML' => true,
                ));
            }

            return false;
        }

        $this->DoInstallFiles();
        COption::SetOptionString($this->MODULE_ID, 'server_url', KOMTETDELIVERY_MODULE_URL);
        COption::SetOptionInt($this->MODULE_ID, 'should_form', 1);
        RegisterModule($this->MODULE_ID);
        RegisterModuleDependences('sale', 'OnShipmentAllowDelivery', $this->MODULE_ID, 'KomtetDelivery', 'handleSalePayOrder');

        $callback_url = array(
            'CONDITION' => '#^/done_order/([0-9a-zA-Z-]+)/#',
            'RULE' => 'ORDER_ID=$1',
            'ID' => 'bitrix:komtet.delivery',
            'PATH' => sprintf('/%s/%s', 'komtet.delivery', 'komtet_delivery_done_order.php'),
            'SORT' => 100,
        );

        CUrlRewriter::Add($callback_url);

        $saleModuleInfo = CModule::CreateModuleObject('sale');
    }

    public function DoInstallFiles()
    {
        foreach ($this->FILES as $file) {
            CopyDirFiles(
                $file['FROM'],
                $file['TO'],
                true,
                true
            );
        }
    }

    public function DoUninstall()
    {
        if (IsModuleInstalled($this->MODULE_ID)) {
            $this->DoUninstallFields();
            COption::RemoveOption($this->MODULE_ID);

            UnRegisterModule($this->MODULE_ID);
            UnRegisterModuleDependences('sale', 'OnShipmentAllowDelivery', $this->MODULE_ID, 'KomtetDelivery', 'handleSalePayOrder');
            $this->DoUninstallDB();
            $this->DoUninstallFiles();
            CUrlRewriter::Delete(array('ID' => 'bitrix:komtet.delivery'));
        }
    }

    public function DoUninstallFiles()
    {
        foreach ($this->FILES as $file) {
            DeleteDirFiles(
                $file['FROM'],
                $file['TO']
            );
        }
        rmdir($this->FILES['tools']['TO']);
    }

    public function DoInstallDB()
    {
        global $DB, $DBType, $APPLICATION;

        $errors = $DB->RunSQLBatch(sprintf('%s/db/%s/install.sql', $this->INSTALL_DIR, $DBType));
        if (empty($errors)) {
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

        if (!CModule::IncludeModule('sale')) {
            return false;
        }

        $personTypeList = CSalePersonType::GetList(
            array(),
            array(),
            false,
            false,
            array()
        );

        if (intval($personTypeList->SelectedRowsCount()) === 0) {
            $APPLICATION->ThrowException(GetMessage('MOD_ERR_PERSON_NOT_FOUND'));

            return false;
        }

        while ($personType = $personTypeList->Fetch()) {
            $groupID = CSaleOrderPropsGroup::Add(array(
                'PERSON_TYPE_ID' => $personType['ID'],
                'NAME' => $this->GROUP_NAME,
                'SORT' => '100',
            ));

            $arFields = array(
                "ADDRESS" => array(
                    "PERSON_TYPE_ID" => $personType["ID"],
                    "NAME" => GetMessage('PROPERTY_ADDRESS'),
                    "TYPE" => "TEXT",
                    "SORT" => "100",
                    "PROPS_GROUP_ID" => $groupID,
                    "CODE" => "kkd_address"
                ),
                'DATE' => array(
                    'PERSON_TYPE_ID' => $personType['ID'],
                    'NAME' => GetMessage('PROPERTY_DATE'),
                    'TYPE' => 'DATE',
                    'SORT' => '100',
                    'PROPS_GROUP_ID' => $groupID,
                    'CODE' => 'kkd_date',
                ),
                'TIME_START' => array(
                    'PERSON_TYPE_ID' => $personType['ID'],
                    'NAME' => GetMessage('PROPERTY_BEGIN_TIME'),
                    'TYPE' => 'ENUM',
                    'SORT' => '100',
                    'PROPS_GROUP_ID' => $groupID,
                    'CODE' => 'kkd_time_start',
                    'DEFAULT_VALUE' => '00:00',
                ),
                'TIME_FINISH' => array(
                    'PERSON_TYPE_ID' => $personType['ID'],
                    'NAME' => GetMessage('PROPERTY_END_TIME'),
                    'TYPE' => 'ENUM',
                    'SORT' => '100',
                    'PROPS_GROUP_ID' => $groupID,
                    'CODE' => 'kkd_time_end',
                    'DEFAULT_VALUE' => '23:00',
                ),
                'COURIER' => array(
                    'PERSON_TYPE_ID' => $personType['ID'],
                    'NAME' => GetMessage('PROPERTY_COURIER'),
                    'TYPE' => 'ENUM',
                    'SORT' => '100',
                    'PROPS_GROUP_ID' => $groupID,
                    'CODE' => 'kkd_courier',
                    'UTIL' => true,
                ),
            );
            foreach ($arFields as $arField) {
                CSaleOrderProps::Add($arField);
            }

            // Получаем поля времени начала и окончания доставки           
            $getStartTimeField = CSaleOrderProps::GetList(
                array(),
                array(
                    'PROPS_GROUP_ID' => $groupID,
                    'NAME' => GetMessage('PROPERTY_BEGIN_TIME'),
                )
            );
            
            $getEndTimeField = CSaleOrderProps::GetList(
                array(),
                array(
                    'PROPS_GROUP_ID' => $groupID,
                    'NAME' => GetMessage('PROPERTY_END_TIME'),
                )
            );
            
            $this->AddValuesForSelectField($getStartTimeField);
            $this->AddValuesForSelectField($getEndTimeField);

        }

        return true;
    }

    public function DoUninstallFields()
    {
        if (!CModule::IncludeModule('sale')) {
            return false;
        }

        $groupList = CSaleOrderPropsGroup::GetList(
            array(),
            array('NAME' => $this->GROUP_NAME),
            false,
            false,
            array()
        );
        while ($group = $groupList->Fetch()) {
            $propertyList = CSaleOrderProps::GetList(
                array(),
                array('PROPS_GROUP_ID' => $group['ID']),
                false,
                false,
                array()
            );
            while ($property = $propertyList->Fetch()) {
                CSaleOrderProps::Delete($property['ID']);
            }
            CSaleOrderPropsGroup::Delete($group['ID']);
        }
    }

    public function CreateTimeTable() 
    {
        $startTime = 0;
        $endTime = 23;
        
        $selectOptions = array();
        for ($i = $startTime; $i <= $endTime; $i++) {
            $time = ($i > 9) ? "{$i}:00" : "0{$i}:00";
            $selectOptions[] = $time;
        }

        return $selectOptions;
    }

    public function AddValuesForSelectField($typeField) 
    {
        //Массив выбора значений (00:00, 01:00 ...)
        $timeTable = $this->CreateTimeTable();

        while ($field = $typeField->Fetch()) {
            CSaleOrderPropsVariant::DeleteAll($field['ID']);
            CSaleOrderProps::Update($field['ID'], array('DEFAULT_VALUE' => ''));

            //Заполнение поля значениями
            foreach ($timeTable as $timeItem) {
                CSaleOrderPropsVariant::Add(
                    array(
                        'ORDER_PROPS_ID' => $field['ID'],
                        'NAME' => $timeItem,
                        'VALUE' => $timeItem,
                    )
                );
            }
            
            // Установка значения по умолчанию
            CSaleOrderProps::Update(
                $field['ID'],
                array('DEFAULT_VALUE' => self::DEFAULT_VALUES[$field['CODE']])
            );
        }
    }
}
