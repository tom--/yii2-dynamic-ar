<?php

namespace spinitron\dynamicAr;

/**
 * DynamicColumn represents a MariaDB dynamic column expression that can
 * be used in a query.
 *
 * DynamicColumn provides an alternative to the `(!attr_name!)` syntax to
 * reference dynamic attributes in queries. For example, the following are
 * equivalent:
 *
 * ```php
 *  $model = Product::find()->where(['(!dimensions.length!)' => 10]);
 *  $model = Product::find()->where([new DynamicColumn('dimensions.length', 'INT') => 10]);
 * ```
 */
class DynamicColumn extends \yii\base\Object
{
    public $name;
    public $type;

    /**
     * @param string $name The dynamic attribute name
     * @param string $type The SQL type of the dynamic attribute
     * @param array $config Object configuration
     */
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
