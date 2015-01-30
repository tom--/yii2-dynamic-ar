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

    public function dataProviderTestWriteRead()
    {
        $data = [
            ['int', 1234],
            ['neg', -4321],
            ['octal', 076543],
            ['hex', 0xbada55],
            ['largeint', 2147483647],
            ['largerin', 9223372036854775807],
            ['neglargein', -2147483648],
            ['neglargerin', -9223372036854775807],
            ['float', 1.1234],
            ['morefloat', 1.123456789012345],
            ['bigfloat', 1.123456789012345e+300],
            ['bignegfloat', -1.123456789012345e+300],
            ['string', 'this is a simple string'],
            ['newlines', 'You can also have embedded newlines in
                strings this way as it is
                okay to do'],
        ];
        $data += include(__DIR__ . '/unicodeStrings.php');
        $binaryString = '';
        for ($i = 0; $i <= 255; $i += 1) {
            $binaryString .= chr($i);
        }
        $data['bonarystring'] = $binaryString;

        return $data;
    }

    /**
     * @dataProvider dataProviderTestWriteRead
     *
     * @param string $name
     * @param mixed $value
     */
    public function testWriteRead($name, $value)
    {
        $product = new Product();
        $product->$name = $value;
        $product->save(false);
        $id = $product->primaryKey;

        unset($product);

        $product = Product::findOne($id);
        $this->assertEquals($value, $product->$name, 'testWriteRead failed for data named: ' . $name);
    }
}
