<?php
/**
 * @author danil danil.kabluk@gmail.com
 */

namespace tests\unit\data\dar;

use \spinitron\dynamicAr\DynamicActiveQuery;
use tests\unit\data\BaseRecord;

class MissingDynColumn extends BaseRecord
{

    public $customColumn;

    public static function dynamicColumn()
    {
        return 'dynamic_columnsss';
    }
}
