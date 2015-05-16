<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace tests\unit\data;

use \spinitron\dynamicAr\DynamicActiveRecord;

/**
 * ActiveRecord is ...
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class BaseRecord extends DynamicActiveRecord
{
    public static $db;

    public static function getDb()
    {
        return self::$db;
    }
}
