<?php

namespace tests\unit;

use tests\unit\data\dar\Supplier;
use Yii;
use spinitron\dynamicAr\DynamicActiveRecord;
use spinitron\dynamicAr\DynamicActiveQuery;
use yiiunit\TestCase;
use tests\unit\data\dar\Product;
use yii\db\Connection;
use yii\db\Query;
use yii\db\ActiveQuery;

class DynamicActiveRecordTest extends TestCase
{
    /*
     * link(,,$extraColumns)
     *
     */
    /** @var Connection */
    protected $db;

    protected function resetFixture()
    {
        $fixture = __DIR__ . '/maria10.sql';
        $this->db->createCommand(file_get_contents($fixture))->execute();
    }

    protected function setUp()
    {
        /** @var Connection */
        $db = Yii::createObject(
            [
                'class' => '\yii\db\Connection',
                'dsn' => 'mysql:host=localhost;dbname=yii2basic',
                'username' => 'y8Y8mtFHAh',
                'password' => '',
            ]
        );
        $this->db = $db;
        Product::$db = $db;
        Supplier::$db = $db;
        $this->resetFixture();
        parent::setUp();
    }


    public function testRead()
    {
        $query = Product::find();
        $this->assertTrue($query instanceof DynamicActiveQuery);
        /** @var Product */
        $product = $query->one();
        $this->assertTrue($product instanceof Product);
    }

    public function testTypes()
    {
        /** @var Product */
        $product = Product::find()->one();
        $this->assertInternalType('string', $product->str);
        $this->assertInternalType('integer', $product->int);
        $this->assertInternalType('float', $product->float);
        $this->assertInternalType('integer', $product->bool);
        $this->assertEmpty($product->null);
        $this->assertInternalType('string', $product->children['str']);
        $this->assertInternalType('integer', $product->children['int']);
        $this->assertInternalType('float', $product->children['float']);
        $this->assertInternalType('integer', $product->children['bool']);
        $this->assertFalse(isset($product->children['null']));
    }

    public function testAsArray()
    {
        /** @var Product */
        $product = Product::find()->one();
        $expect = [
            'name' => 'product1',
            'int' => 123,
            'str' => 'value1',
            'bool' => 1,
            'float' => 123.456,
            'children' => [
                'int' => 123,
                'str' => 'value1',
                'bool' => 1,
                'float' => 123.456,
            ],
        ];
        $this->assertArraySubset($expect, $product->toArray(), true);
    }

    public function testRlations1()
    {
        $product = Product::find()->one();
        $supplier = $product->supplier;
        $this->assertTrue($supplier instanceof Supplier);
        $this->assertEquals('One', $supplier->name);
    }

    public function testRlations2()
    {
    }
}
