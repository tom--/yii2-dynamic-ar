<?php

namespace spinitron\dynamicAr;

/**
 * A DynamicValue object represents the value of a dynamic attribute that DynamicActiveRecord
 * uses directly (unescaped) in SQL instead of using
 * [PDOStatement::bindValue](http://php.net/manual/en/pdostatement.bindvalue.php).
 *
 * Example:
 *
 * ```php
 * $model->width = DynamicValue(123.456);
 * ```
 *
 * You may optionally give an
 * [SQL datatype](https://mariadb.com/kb/en/mariadb/dynamic-columns/#datatypes)
 * as second argument of the constructor, for example:
 *
 * ```php
 * $model->joined = DynamicValue('2015-06-01 12:30:00', 'DATETIME');
 * $model->price = DynamicValue(4.99, 'DECIMAL(6,2)');
 * ```
 *
 * > NOTE: using DynamicValue for the value of a column attribute rather than a dynamic attribute
 * will cause an error.
 *
 * @package spinitron\dynamicAr
 */
class DynamicValue extends \yii\base\Object
{
    /**
     * @var mixed The dynamic attribute value.
     */
    public $value;
    /**
     * @var null|string The value's
     * [SQL datatype](https://mariadb.com/kb/en/mariadb/dynamic-columns/#datatypes) (optional)
     */
    public $type;

    /**
     * Constructor.
     *
     * @param mixed $value The dynamic attribute value.
     * @param string $type The value's
     * [SQL datatype](https://mariadb.com/kb/en/mariadb/dynamic-columns/#datatypes). Omit or
     * set null to not specify a datatype.
     * @param array $config Name-value pairs to initialize object properties.
     */
    public function __construct($value, $type = null, $config = [])
    {
        $this->value = $value;
        $this->type = $type;
        parent::__construct($config);
    }

    /**
     * To string magic method.
     *
     * @return string The dynamic value's SQL expression.
     */
    public function __toString()
    {
        return $this->value . ($this->type !== null ? ' as ' . $this->type : '');
    }
}
