<?php

namespace tests\unit;

use tests\unit\data\ar\ActiveRecord;
use tests\unit\data\ar\Customer;
use tests\unit\data\ar\Item;
use tests\unit\data\ar\Order;
use tests\unit\data\ar\OrderItem;
use tests\unit\data\ar\OrderItemWithNullFK;
use tests\unit\data\ar\OrderWithNullFK;

class ActiveRecordTest extends \yiiunit\framework\db\ActiveRecordTest
{
    protected function setUp()
    {
        static::$params = require(__DIR__ . '/data/config.php');
        parent::setUp();
        ActiveRecord::$db = $this->getConnection();
    }

    public function getCustomerClass()
    {
        return Customer::className();
    }

    public function getItemClass()
    {
        return Item::className();
    }

    public function getOrderClass()
    {
        return Order::className();
    }

    public function getOrderItemClass()
    {
        return OrderItem::className();
    }

    public function getOrderWithNullFKClass()
    {
        return OrderWithNullFK::className();
    }
    public function getOrderItemWithNullFKmClass()
    {
        return OrderItemWithNullFK::className();
    }

}
