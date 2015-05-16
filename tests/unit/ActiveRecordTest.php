<?php

namespace tests\unit;

use tests\unit\data\ar\ActiveRecord;
use tests\unit\data\ar\Customer;
use tests\unit\data\ar\Item;
use tests\unit\data\ar\Order;
use tests\unit\data\ar\OrderItem;
use tests\unit\data\ar\OrderItemWithNullFK;
use tests\unit\data\ar\OrderWithNullFK;
use yiiunit\TestCase;

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

	public function testFindAsArray()
	{
		/* @var $customerClass \yii\db\ActiveRecordInterface */
		$customerClass = $this->getCustomerClass();

		// asArray
		$customer = $customerClass::find()->where(['id' => 2])->asArray()->one();
		// since address is commented in the schema - it should be saved as a dynamic column
		$this->assertEquals([
			'id' => 2,
			'email' => 'user2@example.com',
			'name' => 'user2',
			'status' => 1,
			'profile_id' => null,
			'dynamic_columns' => '{"address":"address2"}',
		], $customer);

		// find all asArray
		$customers = $customerClass::find()->asArray()->all();
		$this->assertEquals(3, count($customers));

		// since address is commented in the schema - it should be saved as a dynamic column
		$this->assertArrayHasKey('dynamic_columns', $customers[0]);
		$this->assertArrayNotHasKey('address', $customers[0]);

		$this->assertArrayHasKey('id', $customers[0]);
		$this->assertArrayHasKey('name', $customers[0]);
		$this->assertArrayHasKey('email', $customers[0]);
		$this->assertArrayHasKey('status', $customers[0]);

		// since address is commented in the schema - it should be saved as a dynamic column
		$this->assertArrayHasKey('dynamic_columns', $customers[1]);
		$this->assertArrayNotHasKey('address', $customers[1]);

		$this->assertArrayHasKey('id', $customers[1]);
		$this->assertArrayHasKey('name', $customers[1]);
		$this->assertArrayHasKey('email', $customers[1]);
		$this->assertArrayHasKey('status', $customers[1]);

		// since address is commented in the schema - it should be saved as a dynamic column
		$this->assertArrayHasKey('dynamic_columns', $customers[2]);
		$this->assertArrayNotHasKey('address', $customers[2]);

		$this->assertArrayHasKey('id', $customers[2]);
		$this->assertArrayHasKey('name', $customers[2]);
		$this->assertArrayHasKey('email', $customers[2]);
		$this->assertArrayHasKey('status', $customers[2]);
	}

	public function testFindIndexByAsArray()
	{
		/* @var $customerClass \yii\db\ActiveRecordInterface */
		$customerClass = $this->getCustomerClass();

		/* @var $this TestCase */
		// indexBy + asArray
		$customers = $customerClass::find()->asArray()->indexBy('name')->all();
		$this->assertEquals(3, count($customers));
		// since address is commented in the schema - it should be saved as a dynamic column
		$this->assertArrayHasKey('dynamic_columns', $customers['user1']);
		$this->assertArrayNotHasKey('address', $customers['user1']);

		$this->assertArrayHasKey('id', $customers['user1']);
		$this->assertArrayHasKey('name', $customers['user1']);
		$this->assertArrayHasKey('email', $customers['user1']);
		$this->assertArrayHasKey('status', $customers['user1']);

		// since address is commented in the schema - it should be saved as a dynamic column
		$this->assertArrayHasKey('dynamic_columns', $customers['user2']);
		$this->assertArrayNotHasKey('address', $customers['user2']);

		$this->assertArrayHasKey('id', $customers['user2']);
		$this->assertArrayHasKey('name', $customers['user2']);
		$this->assertArrayHasKey('email', $customers['user2']);
		$this->assertArrayHasKey('status', $customers['user2']);

		// since address is commented in the schema - it should be saved as a dynamic column
		$this->assertArrayHasKey('dynamic_columns', $customers['user3']);
		$this->assertArrayNotHasKey('address', $customers['user3']);

		$this->assertArrayHasKey('id', $customers['user3']);
		$this->assertArrayHasKey('name', $customers['user3']);
		$this->assertArrayHasKey('email', $customers['user3']);
		$this->assertArrayHasKey('status', $customers['user3']);

		// indexBy callable + asArray
		$customers = $customerClass::find()->indexBy(function ($customer) {
			return $customer['id'] . '-' . $customer['name'];
		})->asArray()->all();
		$this->assertEquals(3, count($customers));
		// since address is commented in the schema - it should be saved as a dynamic column
		$this->assertArrayHasKey('dynamic_columns', $customers['1-user1']);
		$this->assertArrayNotHasKey('address', $customers['1-user1']);

		$this->assertArrayHasKey('id', $customers['1-user1']);
		$this->assertArrayHasKey('name', $customers['1-user1']);
		$this->assertArrayHasKey('email', $customers['1-user1']);
		$this->assertArrayHasKey('status', $customers['1-user1']);

		// since address is commented in the schema - it should be saved as a dynamic column
		$this->assertArrayHasKey('dynamic_columns', $customers['1-user1']);
		$this->assertArrayNotHasKey('address', $customers['1-user1']);

		$this->assertArrayHasKey('id', $customers['2-user2']);
		$this->assertArrayHasKey('name', $customers['2-user2']);
		$this->assertArrayHasKey('email', $customers['2-user2']);
		$this->assertArrayHasKey('status', $customers['2-user2']);

		// since address is commented in the schema - it should be saved as a dynamic column
		$this->assertArrayHasKey('dynamic_columns', $customers['1-user1']);
		$this->assertArrayNotHasKey('address', $customers['1-user1']);

		$this->assertArrayHasKey('id', $customers['3-user3']);
		$this->assertArrayHasKey('name', $customers['3-user3']);
		$this->assertArrayHasKey('email', $customers['3-user3']);
		$this->assertArrayHasKey('status', $customers['3-user3']);
	}


}
