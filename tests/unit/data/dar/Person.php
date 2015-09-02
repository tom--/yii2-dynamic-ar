<?php

namespace tests\unit\data\dar;

use \spinitron\dynamicAr\DynamicActiveQuery;
use spinitron\dynamicAr\DynamicActiveRecord;
use tests\unit\data\BaseRecord;

class Person extends Supplier
{
    public static function tableName()
    {
        return 'supplier';
    }

    public static function dynamicColumn()
    {
        return 'dynamic_columns';
    }

    public function scenarios()
    {
        return [
            'none' => [],
            'boss' => ['boss.first', 'boss.last'],
            'all' => ['boss.first', 'boss.last', 'address.street', 'address.city'],
        ];
    }

    public function rules()
    {
        return [
            [['boss.last', 'address.street', 'address.city'], 'required'],
            [['boss.first', 'boss.last'], 'string', 'max' => 5],
        ];
    }
}
