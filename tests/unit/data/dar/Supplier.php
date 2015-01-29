<?php

namespace tests\unit\data\dar;

use \spinitron\dynamicAr\DynamicActiveRecord;
use \spinitron\dynamicAr\DynamicActiveQuery;

class Supplier extends DynamicActiveRecord
{
    public static $db;

    public static function getDb()
    {
        return self::$db;
    }

    public static function tableName()
    {
        return 'supplier';
    }

    public static function dynamicColumn()
    {
        return 'dynamic_columns';
    }

    /**
     * @return DynamicActiveQuery
     */
    public function getProducts()
    {
        return $this->hasMany(Product::className(), ['supplier_id' => 'id']);
    }
}
