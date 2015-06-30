<?php

namespace spinitron\dynamicAr;

class DynamicColumn extends \yii\base\Object
{
    public $name;
    public $type;

    public function __construct($name, $type = 'CHAR', $config = [])
    {
        $this->name = $name;
        $this->type = $type;
        parent::__construct($config);
    }

    public function __toString()
    {
        return 'COLUMN_GET(' . $this->name . ' as ' . $this->type . ')';
    }
}
