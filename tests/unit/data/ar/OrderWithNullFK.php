<?php

namespace tests\unit\data\ar;

use tests\unit\data\BaseRecord;

/**
 * Class Order
 *
 * @property integer $id
 * @property integer $customer_id
 * @property integer $created_at
 * @property string $total
 */
class OrderWithNullFK extends BaseRecord
{
    public static function tableName()
    {
        return 'order_with_null_fk';
    }


}
