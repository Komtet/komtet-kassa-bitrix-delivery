<?php

use Bitrix\Main\Entity;
use Bitrix\Main\Type;

class KomtetDeliveryReportsTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'komtet_kassa_delivery';
    }

    public static function getMap()
    {
        return array(
            new Entity\IntegerField('id', array(
                'primary' => true,
                'column_name' => 'id'
            )),
            new Entity\IntegerField('order_id', array(
                'required' => true,
                'column_name' => 'order_id'
            )),
            new Entity\IntegerField('kk_id', array(
                'required' => true,
                'column_name' => 'kk_id'
            )),
            new Entity\StringField('request', array(
                'default_value' => '',
                'required' => false,
                'column_name' => 'request'
            )),
            new Entity\StringField('response', array(
                'default_value' => '',
                'required' => false,
                'column_name' => 'response'
            )),
        );
    }
}
