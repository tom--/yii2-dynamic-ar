<?php

namespace tests\unit;

use tests\unit\data\dar\Person;
use tests\unit\data\dar\Product;
use tests\unit\data\BaseRecord;
use yii\db\Connection;
use yiiunit\framework\db\DatabaseTestCase;

class DynamicActiveRecordAccessTest extends DatabaseTestCase
{
    /** @var Connection */
    static $db;

    protected function setUp()
    {
        static::$params = require(__DIR__ . '/data/config.php');
        parent::setUp();
        self::$db = BaseRecord::$db = $this->getConnection(self::$db === null, self::$db === null);
    }

    public function testIsset()
    {
        $p = new Person();
        $p->setAttribute('a.b.c', null);
        $this->assertTrue($p->issetAttribute('a.b'));
        $this->assertFalse($p->issetAttribute('a.b.c'));
    }

    protected static $postInput = [
        'Person' => [
            'boss.first' => 'Tom',
            'boss.last' => 'Worster',
            'address.street' => '123 Foo St',
            'address.city' => 'Barton',
        ]
    ];
    protected static $toArray = [
        'boss' => [
            'first' => 'Tom',
            'last' => 'Worster',
        ],
        'address' => [
            'street' => '123 Foo St',
            'city' => 'Barton',
        ],
    ];

    public function testLoad()
    {
        $p = new Person();
        $p->scenario = 'all';
        $this->assertTrue($p->load(self::$postInput));
        $this->assertEquals(self::$toArray, $p->toArray());
    }

    public function testMassive()
    {
        $p = new Person();
        $p->scenario = 'all';
        $p->attributes = self::$postInput['Person'];
        $this->assertEquals(self::$toArray, $p->toArray());
    }

    public function testSafe()
    {
        $p = new Person();
        $p->load(self::$postInput);
        $this->assertEmpty($p->toArray());

        $p->scenario = 'boss';
        $p->load(self::$postInput);
        $this->assertEquals(['boss' => self::$toArray['boss']], $p->toArray());

        $p->scenario = 'all';
        $p->load(self::$postInput);
        $this->assertEquals(self::$toArray, $p->toArray());
    }

    public function testValidationSome()
    {
        $p = new Person();
        $p->scenario = 'boss';
        $p->load(self::$postInput);

        $this->assertTrue($p->validate(['boss.first']));

        $this->assertEmpty($p->errors);
        $this->assertFalse($p->validate(['boss.last']));
        $this->assertEquals(['boss.last'], array_keys($p->errors));
    }

    public function testValidationAll()
    {
        $p = new Person();
        $p->scenario = 'all';
        $this->assertTrue($p->load(self::$postInput));

        $this->assertFalse($p->validate());
        $this->assertEquals(['boss.last'], array_keys($p->errors));
    }

    public function testNested()
    {
        $product = new Product();
        $product->person = new Person(['scenario' => 'all']);
        $this->assertTrue($product->person->load(self::$postInput));

        $this->assertFalse($product->person->validate());
        $this->assertEquals(['boss.last'], array_keys($product->person->errors));
    }

    public function testNestedSave()
    {
        $product = new Product();
        $product->person = new Person(['scenario' => 'all']);
        $product->person->load(self::$postInput);

        $this->assertTrue($product->save(false));
        $product2 = Product::findOne($product->id);
        $this->assertNotNull($product2);

        $this->assertEquals(self::$postInput, $product2->person);
    }

    /**
     * Also test __get()
     */
    public function testSetGetAttribute()
    {
        $array = [
            'a' => 1,
            'b' => ['a' => 1, 'b' => 2],
            'c' => [
                'a' => 1,
                'b' => ['a' => 1, 'b' => 2],
                'c' => [
                    'a' => 1,
                    'b' => ['a' => 1, 'b' => 2],
                    'c' => [
                        'a' => 1,
                        'b' => ['a' => 1, 'b' => 2],
                        'c' => 3
                    ]
                ]
            ]
        ];

        $p = new Product();
        $p->setAttribute('array', $array);

        $array['d'] = 4;
        $p->setAttribute('array.d', 4);
        $this->assertEquals($array, $p->array);
        $this->assertEquals($array, $p->getAttribute('array'));
        $this->assertEquals(4, $p->getAttribute('array.d'));

        $array['e']['a']['b']['c'] = 5;
        $p->setAttribute('array.e.a.b.c', 5);
        $this->assertEquals($array, $p->array);
        $this->assertEquals($array, $p->getAttribute('array'));
        $this->assertEquals(5, $p->getAttribute('array.e.a.b.c'));

        $array['c']['b']['c'] = 6;
        $p->setAttribute('array.c.b.c', 6);
        $this->assertEquals($array, $p->array);
        $this->assertEquals($array, $p->getAttribute('array'));
        $this->assertEquals(6, $p->getAttribute('array.c.b.c'));

        $array['c']['c']['b']['d'] = ['x' => 1, 'y' => ['z' => 2]];
        $p->setAttribute('array.c.c.b.d', ['x' => 1, 'y' => ['z' => 2]]);
        $this->assertEquals($array, $p->array);
        $this->assertEquals($array, $p->getAttribute('array'));
        $this->assertEquals(['x' => 1, 'y' => ['z' => 2]], $p->getAttribute('array.c.c.b.d'));

        $array['c']['c']['c'] = 7;
        $p->setAttribute('array.c.c.c', 7);
        $this->assertEquals($array, $p->array);
        $this->assertEquals($array, $p->getAttribute('array'));
        $this->assertEquals(7, $p->getAttribute('array.c.c.c'));

        $array['c']['c'] = [9, 8, 7, 6];
        $p->setAttribute('array.c.c', [9, 8, 7, 6]);
        $this->assertEquals($array, $p->array);
        $this->assertEquals($array, $p->getAttribute('array'));
        $this->assertEquals([9, 8, 7, 6], $p->getAttribute('array.c.c'));
    }

    public function testIssetUnsetAttribute()
    {
        $p = new Product();

        $this->assertFalse($p->issetAttribute('doesntexist'));
        $p->unsetAttribute('doesntexist');
        $this->assertFalse($p->issetAttribute('doesntexist'));

        // unset column attribute
        $this->assertFalse($p->issetAttribute('name'));

        $p->name = 'spinitron';
        $this->assertTrue($p->issetAttribute('name'));
        $p->unsetAttribute('name');
        $this->assertFalse($p->issetAttribute('name'));

        $p->setAttribute('array.c.c.b.d', ['x' => 1, 'y' => ['z' => 2]]);
        $this->assertTrue($p->issetAttribute('array.c.c.b.d.y.z'));
        $this->assertFalse($p->issetAttribute('array.c.c.b.d.doesntexist'));

        $p->unsetAttribute('array.c.c.b.d.doesntexist');
        $this->assertFalse($p->issetAttribute('array.c.c.b.d.doesntexist'));

        $p->unsetAttribute('array.c.c.b');
        $this->assertFalse($p->issetAttribute('array.c.c.b.d.doesntexist'));
        $this->assertFalse($p->issetAttribute('array.c.c.b.d.y.z'));
        $this->assertFalse($p->issetAttribute('array.c.c.b.d.y'));
        $this->assertFalse($p->issetAttribute('array.c.c.b.d'));
        $this->assertFalse($p->issetAttribute('array.c.c.b'));
        $this->assertTrue($p->issetAttribute('array.c.c'));
    }

    public function testGetNullAttribute()
    {
        $p = new Product();
        $this->assertNull($p->doesntexist);
        $this->assertNull($p->name);
        $p->setAttribute('array.c.c.b.d', ['x' => 1, 'y' => ['z' => 2]]);
        $this->assertNotNull($p->getAttribute('array.c.c.b.d.y.z'));
        $this->assertNull($p->getAttribute('array.c.c.b.d.y.z.doesntexist'));
        $this->assertNull($p->getAttribute('array.c.doesntexist'));
        $p->unsetAttribute('array.c.c.b');
        $this->assertNull($p->getAttribute('array.c.c.b.d.y.z'));
        $this->assertNull($p->getAttribute('array.c.c.b'));
    }

    public function testWonkyAttributeNames()
    {
        $p = new Product();
        $p->__set('4chan', 'sucks');
        $this->assertTrue($p->__isset('4chan'));
        $this->assertEquals('sucks', $p->__get('4chan'));
    }
}