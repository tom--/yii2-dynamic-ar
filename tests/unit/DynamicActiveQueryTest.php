<?php
/**
 * @author danil danil.kabluk@gmail.com
 */

namespace tests\unit;

use spinitron\dynamicAr\DynamicActiveQuery;
use yii\db\Query;
use yiiunit\framework\db\DatabaseTestCase;
use tests\unit\data\dar\Product;

class DynamicActiveQueryTest extends DatabaseTestCase
{

    protected function setUp()
    {
        static::$params = require(__DIR__ . '/data/config.php');
        parent::setUp();
        Product::$db = $this->getConnection();
    }

    public function testDynamicSelect()
    {
        // default
        $query = new DynamicActiveQuery(Product::className());
        $query->select('*');
        $command = $query->createCommand();
        $this->assertEquals('SELECT *, COLUMN_JSON(`dynamic_columns`) AS `dynamic_columns` FROM `product`', $command->getRawSql());

        // one dynamic attribute
        $query = new DynamicActiveQuery(Product::className());
        $query->select(['{cost|decimal(6,2)}']);
        $command = $query->createCommand();
        $this->assertEquals("SELECT COLUMN_GET(dynamic_columns, 'cost' AS decimal(6,2)) FROM `product`", $command->getRawSql());

        // few dynamic attributes
        $query = new DynamicActiveQuery(Product::className());
        $query->select(['{cost|decimal(6,2)}, {price.wholesale.12|decimal(6,2)}']);
        $command = $query->createCommand();
        $this->assertEquals("SELECT COLUMN_GET(dynamic_columns, 'cost' AS decimal(6,2)), COLUMN_GET(COLUMN_GET(COLUMN_GET(dynamic_columns, 'price' AS decimal(6,2)), 'wholesale' AS decimal(6,2)), '12' AS decimal(6,2)) FROM `product`", $command->getRawSql());

        // few dynamic, one static
        $query = new DynamicActiveQuery(Product::className());
        $query->select(['{cost|decimal(6,2)}, {price.wholesale.12|decimal(6,2)}, id']);
        $command = $query->createCommand();
        $this->assertEquals("SELECT COLUMN_GET(dynamic_columns, 'cost' AS decimal(6,2)), COLUMN_GET(COLUMN_GET(COLUMN_GET(dynamic_columns, 'price' AS decimal(6,2)), 'wholesale' AS decimal(6,2)), '12' AS decimal(6,2)), id FROM `product`", $command->getRawSql());
    }

    public function testWhere()
    {
        $query = new DynamicActiveQuery(Product::className());
        $query->select('*')
            ->where('{one.two|char} = t');
        $command = $query->createCommand();
        $this->assertEquals("SELECT * FROM `product` WHERE COLUMN_GET(COLUMN_GET(dynamic_columns, 'one' AS char), 'two' AS char) = t",
            $command->getRawSql());

        $query->andWhere('{one.three|int} = 5');
        $command = $query->createCommand();
        $this->assertEquals("SELECT * FROM `product` WHERE (COLUMN_GET(COLUMN_GET(dynamic_columns, 'one' AS char), 'two' AS char) = t) AND (COLUMN_GET(COLUMN_GET(dynamic_columns, 'one' AS int), 'three' AS int) = 5)",
            $command->getRawSql());
    }

    /**
     * Every type listed in $this->types() should be recognised as dynamic field type
     */
    public function testTypesProcessing()
    {
        // it's enough to just check select - logic is similar for the whole sql query
        $query = new DynamicActiveQuery(Product::className());

        foreach ($this->types() as $k => $possibleTypes) {
            foreach ($possibleTypes as $type) {
                $query->select(["{test|$type}"]);
                $command = $query->createCommand();

                $sql = $command->getRawSql();
                $this->assertNotContains("{test|$type}", $sql,
                    "Type $type should be processed, there shouldn't be any user's dynamic queries");
                $this->assertContains("as $type", $sql, "Type $type should be processed", true);
            }
        }
    }

    private function types()
    {
        return [
            'binary' => [
                'binary',
                'binary(5)',
            ],
            'char' => [
                'char',
                'char(5)',
            ],
            'time' => [
                'time',
                'time(5)',
            ],
            'datetime' => [
                'datetime',
                'datetime(5)',
            ],
            'date' => ['date'],
            'decimal' => [
                'decimal',
                'decimal(5)',
                'decimal(5,6)',
            ],
            'double' => [
                'double',
                'double(5,6)',
            ],
            'int' => [
                'int',
                'integer',
            ],
            'signed' => [
                'signed',
                'signed int',
                'signed integer',
            ],
            'unsigned' => [
                'unsigned',
                'unsigned int',
                'unsigned integer',
            ],
        ];
    }
}