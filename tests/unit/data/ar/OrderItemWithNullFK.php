<?php

namespace tests\unit\data\ar;

use tests\unit\data\BaseRecord;

/**
 * Class OrderItem
 *
 * @property integer $order_id
 * @property integer $item_id
 * @property integer $quantity
 * @property string $subtotal
 */
class OrderItemWithNullFK extends BaseRecord
{
    public static function tableName()
    {
        return 'order_item_with_null_fk';
    }

}
