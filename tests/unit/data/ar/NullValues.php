<?php

namespace tests\unit\data\ar;

use tests\unit\data\BaseRecord;

/**
 * Class NullValues
 *
 * @property integer $id
 * @property integer $var1
 * @property integer $var2
 * @property integer $var3
 * @property string $stringcol
 */
class NullValues extends BaseRecord
{
    public static function tableName()
    {
        return 'null_values';
    }
}
