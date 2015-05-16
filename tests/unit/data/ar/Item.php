<?php

namespace tests\unit\data\ar;

use tests\unit\data\BaseRecord;

/**
 * Class Item
 *
 * @property integer $id
 * @property string $name
 * @property integer $category_id
 */
class Item extends BaseRecord
{
    public static function tableName()
    {
        return 'item';
    }

    public function getCategory()
    {
        return $this->hasOne(Category::className(), ['id' => 'category_id']);
    }
}
