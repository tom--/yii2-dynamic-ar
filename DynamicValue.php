<?php

namespace spinitron\dynamicAr;

class DynamicValue extends \yii\base\Object
{
    public $value;
    public $type;

    public function __construct($value, $type = 'CHAR', $config = [])
    {
        $this->value = $value;
        $this->type = $type;
        parent::__construct($config);
    }

    public function __toString()
    {
        return $this->value . ' as ' . $this->type;
    }
}
