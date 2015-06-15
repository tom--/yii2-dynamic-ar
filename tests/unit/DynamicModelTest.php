<?php

namespace tests\unit;

use tests\unit\data\dar\Person;
use tests\unit\data\dar\Product;
use tests\unit\data\BaseRecord;
use tests\unit\data\dar\Supplier;
use yii\db\Connection;
use yiiunit\framework\db\ActiveRecordTest;

class DynamicModelTest extends ActiveRecordTest
{
    /** @var Connection */
    protected $db;

    protected function setUp()
    {
        if ($this->db === null) {
            static::$params = require(__DIR__ . '/data/config.php');
            parent::setUp();
            $this->db = BaseRecord::$db = $this->getConnection();
        }
    }

    public function testLoad()
    {
        $post = [
            'boss.first' => 'Tom',
            'boss.last' => 'Worster',
            'address.street' => '123 Foo St',
            'address.city' => 'Barton',
        ];

        $model = new Person();
        $this->assertFalse($model->load($post));
        $this->assertEmpty($model->toArray());

        $model->scenario = 'boss';
//        $this->assertTrue($model->load($post));
        $model->attributes = $post;
        $this->assertEquals(
            [
                'boss.first' => 'Tom',
                'boss.last' => 'Worster',
            ],
            $model->toArray()
        );

        $model->scenario = 'all';
        $this->assertTrue($model->load($post));
        $this->assertEquals($post, $model->toArray());
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

    /**
     * @expectedException \yii\base\InvalidCallException
     * @expectedExceptionMessage Invalid attribute name "4chan"
     */
    public function testInvalidAttributeName()
    {
        $p = new Product();
        $p->__set('4chan', 'sucks');
    }
}