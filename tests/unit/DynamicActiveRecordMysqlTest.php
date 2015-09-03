<?php
/**
 * @link https://github.com/tom--/dynamic-ar
 * @copyright Copyright (c) 2015 Spinitron LLC
 * @license http://opensource.org/licenses/ISC
 */

namespace tests\unit;

use Yii;

/**
 * @author Tom Worster <fsb@thefsb.org>
 * @author Danil Zakablukovskii danil.kabluk@gmail.com
 */
class DynamicActiveRecordMysqlTest extends DynamicActiveRecordTest
{
    protected $driverName = 'mysql';

    public function testReadBinary()
    {
        $json = $this->db->createCommand(
            'select column_json(dynamic_columns) from product where id=10'
        )->queryScalar();
        // todo need an assertion
    }

    public function testCreateColumnInBeforeSave()
    {
        $product = new Product;
        $product->dynamic_column = 123;
        $product->child = [
            'column' => 'value',
        ];

        $product->beforeSave(true);

        /** @var \yii\db\Expression $expression */
        $expression = $product->dynamic_columns;
        $this->assertContains('COLUMN_CREATE', $expression->expression);
    }
}
