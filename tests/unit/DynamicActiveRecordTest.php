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

    const BINARY_STRING = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3a\x3b\x3c\x3d\x3e\x3f\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4a\x4b\x4c\x4d\x4e\x4f\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5a\x5b\x5c\x5d\x5e\x5f\x60\x61\x62\x63\x64\x65\x66\x67\x68\x69\x6a\x6b\x6c\x6d\x6e\x6f\x70\x71\x72\x73\x74\x75\x76\x77\x78\x79\x7a\x7b\x7c\x7d\x7e\x7f\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f\xa0\xa1\xa2\xa3\xa4\xa5\xa6\xa7\xa8\xa9\xaa\xab\xac\xad\xae\xaf\xb0\xb1\xb2\xb3\xb4\xb5\xb6\xb7\xb8\xb9\xba\xbb\xbc\xbd\xbe\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf7\xf8\xf9\xfa\xfb\xfc\xfd\xfe\xff";

    protected function resetFixture()
    {
        $fixture = __DIR__ . '/maria10.sql';
        $this->db->createCommand(file_get_contents($fixture))->execute();
    }

    protected static $resetFixture = true;

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
        if (self::$resetFixture) {
            $this->resetFixture();
        }
        parent::setUp();
    }

    public function testReadBinary()
    {
        $vars = $this->db->createCommand('show variables like "char%"')->queryAll();
        var_dump($vars);
        $json = $this->db->createCommand('select column_json(dynamic_columns) from product where id=10')->queryScalar();
        self::hexDump($json);
        var_dump($json);
        var_dump(json_decode($json));
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

    public function dataProviderTestMariaArrayEncoding()
    {
        $tests = [
            [[1]],
            [['x' => 'asd']],
            [['x' => "asd\xC1\xC2\xC3asd"]],
            [["asd\xC1\xC2\xC3asd" => 'qwert']],
            [[1, "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3", 3, 'four' => true]],
            [[[[1, "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3", 3, 'four' => true]]]],
            [[1, [2, [3, [4, [1, "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3", 3, 'four' => true]]]]]],
            [
                1,
                "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3",
                "_x\xE1" => [1, "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3", 3, 'four' => true],
                3,
                'four' => true
            ],
            [
                1,
                "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3",
                "_x\xE1" => [
                    1,
                    "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3",
                    "_x\xE1" => [1, "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3", 3, 'four' => true],
                    3,
                    'four' => true
                ],
                3,
                'four' => true
            ],
            [
                1,
                "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3",
                "_x\xE1" => [
                    1,
                    "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3",
                    "_x\xE1" => [
                        1,
                        "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3",
                        "_x\xE1" => [
                            1,
                            "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3",
                            "_x\xE1" => [1, "asd\xC1\xC2\xC3asd" => "qwert\xD1\xD2\xD3", 3, 'four' => true],
                            3,
                            'four' => true
                        ],
                        3,
                        'four' => false
                    ],
                    3,
                    'four' => true
                ],
                3,
                'four' => false
            ],
        ];
        return array_merge($tests);
    }

    public function dataProviderTestWriteRead()
    {
        return [['bin', "\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f"]];
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
            ['evenmorefloat', 1.123456789012345678901234567890],
            ['bigfloat', 1.123456789012345e+300],
            ['bignegfloat', -1.123456789012345e+300],
            ['string1', 'this is a simple string'],
            ['string2', 'string with a \\ backslash in it'],
            ['string3', 'string with a \' quote char in it'],
            ['string4', 'string with a \" doublequote char in it'],
            ['string5', 'string with a / slash in it'],
            ['string5', "string with a \n newline in it"],
            ['string5', "string with a \r return in it"],
            ['string5', "string with a \t tab in it"],
            ['string5', "string with a \v vertical tab in it"],
            ['string5', "string with a \e escape in it"],
            ['string5', "string with a \f form feed in it"],
            [
                'newlines',
                'You can also have embedded newlines in
                strings this way as it is
                okay to do'
            ],
            ['fakeoctet', DynamicActiveRecord::DATA_URI_PREFIX . 'This is my string'],
            ['octet', DynamicActiveRecord::DATA_URI_PREFIX . base64_encode('This is my string')],
        ];

//        $data = array_merge($data, include(__DIR__ . '/unicodeStrings.php'));

        $data[] = ['binarystring', self::BINARY_STRING];

        foreach ($this->dataProviderTestMariaArrayEncoding() as $i => $array) {
            $data[] = ['array' . $i, $array];
        }

        return $data;
    }

    protected static function hexDump($data, $newline = "\n")
    {
        static $from = '';
        static $to = '';
        static $width = 16; # number of bytes per line
        static $pad = '.'; # padding for non-visible characters

        if ($from === '') {
            for ($i = 0; $i <= 0xFF; $i++) {
                $from .= chr($i);
                $to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
            }
        }

        $hex = str_split(bin2hex($data), $width * 2);
        $chars = str_split(strtr($data, $from, $to), $width);

        $offset = 0;
        foreach ($hex as $i => $line) {
            echo sprintf('%6X', $offset) . ' : '
                . implode(' ', str_split(str_pad($line, 2 * $width), 2))
                . ' [' . str_pad($chars[$i], $width) . ']' . $newline;
            $offset += $width;
        }
    }

    /**
     * @dataProvider dataProviderTestWriteRead
     *
     * @param string $name
     * @param mixed $value
     */
    public function testMariaEncoding($name, $value)
    {
        self::$resetFixture = false;
        $bar = DynamicActiveRecord::encodeForMaria($value);
        $bar = json_encode($bar);
        $bar = json_decode($bar);
        $bar = DynamicActiveRecord::decodeForMaria($bar);
        $this->assertSame(
            $value,
            DynamicActiveRecord::decodeForMaria(DynamicActiveRecord::encodeForMaria($value))
        );
    }

    /**
     * @dataProvider dataProviderTestMariaArrayEncoding
     *
     * @param array $expected
     */
    public function testMariaArrayEncoding($expected)
    {
        self::$resetFixture = false;
        $actual = $expected;
        DynamicActiveRecord::encodeArrayForMaria($actual);
        $actual = json_encode($actual);
        $actual = json_decode($actual, true);
        DynamicActiveRecord::decodeArrayForMaria($actual);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider dataProviderTestWriteRead
     *
     * @param string $name
     * @param mixed $value
     */
    public function testWriteRead($name, $value)
    {
        self::$resetFixture = false;
        $product = new Product();
        $product->$name = $value;

        $product->save(false);
        $id = $product->primaryKey;
        unset($product);

        $product = Product::findOne($id);

        self::hexDump($product->dynamic_columns);

        $this->assertEquals(
            $value,
            $product->$name,
            'data name: ' . $name,
            is_float($value) ? abs($value) / 10e+12 : 0.0
        );
        unset($product);
    }
}