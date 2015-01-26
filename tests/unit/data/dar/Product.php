<?php

namespace tests\unit\data\dar;

use \spinitron\dynamicAr\DynamicActiveRecord;

class Product extends DynamicActiveRecord
{
    public static $db;

    public static function getDb()
    {
        return self::$db;
    }

    public static function tableName()
    {
        return 'product';
    }

    public static function dynamicColumn()
    {
        return 'dynamic_columns';
    }
}
