<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace tests\unit\data\ar;

use tests\unit\data\BaseRecord;

/**
 * Class Category.
 *
 * @property integer $id
 * @property string $name
 */
class Category extends BaseRecord
{
    public static function tableName()
    {
        return 'category';
    }

    public function getItems()
    {
        return $this->hasMany(Item::className(), ['category_id' => 'id']);
    }

    public function getLimitedItems()
    {
        return $this->hasMany(Item::className(), ['category_id' => 'id'])
            ->onCondition(['item.id' => [1, 2, 3]]);
    }

    public function getOrderItems()
    {
        return $this->hasMany(OrderItem::className(), ['item_id' => 'id'])->via('items');
    }

    public function getOrders()
    {
        return $this->hasMany(Order::className(), ['id' => 'order_id'])->via('orderItems');
    }
}
