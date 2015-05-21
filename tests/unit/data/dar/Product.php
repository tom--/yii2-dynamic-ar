<?php

namespace tests\unit\data\dar;

use \spinitron\dynamicAr\DynamicActiveQuery;
use tests\unit\data\BaseRecord;

class Product extends BaseRecord
{

    public $customColumn;

    public static function dynamicColumn()
    {
        return 'dynamic_columns';
    }

    /**
     * @return DynamicActiveQuery
     */
    public function getSupplier()
    {
        return $this->hasOne(Supplier::className(), ['id' => 'supplier_id']);
    }
}
