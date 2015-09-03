<?php
/**
 * @link https://github.com/tom--/dynamic-ar
 * @copyright Copyright (c) 2015 Spinitron LLC
 * @license http://opensource.org/licenses/ISC
 */

namespace spinitron\dynamicAr;

use Yii;
use yii\base\Exception;
use yii\base\UnknownPropertyException;
use yii\db\ActiveRecord;

/**
 * DynamicActiveRecord represents relational data with structured dynamic attributes
 * in addition to column attributes supported by ActiveRecord.
 *
 * DynamicActiveRecord adds structured dynamic attributes to Yii 2 ActiveRecord a bit
 * like adding a document, in the sense of a NoSQL document store database, to each
 * SQL table record. At present this is implemented for
 * [Maria 10.0+ Dynamic Columns](https://mariadb.com/kb/en/mariadb/dynamic-columns/).
 *
 * You can read and write attributes of a DynamicActiveRecord model that are not
 * instance variables, column attributes or virtual attribute. In other words, you can make
 * up attribute names on-the-fly, assign values and they are stored in the database. You can
 * then involve these dynamic attributes in queries using [[DynamicActiveQuery]].
 *
 * Dynamic attributes may also be data structures in the form of PHP arrays, elements of
 * which can be accessed through dotted attribute notation.
 *
 * ```php
 * $model->specs = ['dimensions' => ['length' => 20]];
 * $model->setAttribute('specs.dimensions.width', 4);
 * $model->setAttribute('specs.color', 'blue');
 * // $model->specs now has the value
 * // ['dimensions' => ['length' => 20, 'width' => 4], 'color' => 'blue']
 * ```
 *
 * Model classes must implement the [[dynamicColumn()]] method to specify the name of the
 * table column containing the serialized dynamic attributes.
 *
 * @author Tom Worster <fsb@thefsb.org>
 */
class DynamicActiveRecord extends ActiveRecord
{
    private $_dynamicAttributes = [];

    protected static $_encoder;

    /**
     * @return encoder\EncoderInterface
     * @throws \yii\base\NotSupportedException
     * @throws \yii\base\InvalidConfigException
     */
    public static function getDynamicEncoder()
    {
        if (static::$_encoder !== null) {
            return static::$_encoder;
        }
        $driver = static::getDb()->getDriverName();
        $encoderMap = static::getEncoderMap();
        if (!isset($encoderMap[$driver])) {
            throw new \yii\base\NotSupportedException("DynamicActiveRecord does not support '$driver' DBMS.");
        }
        $config = !is_array($encoderMap[$driver]) ? ['class' => $encoderMap[$driver]] : $encoderMap[$driver];
        $config['modelClass'] = static::className();

        return static::$_encoder = Yii::createObject($config);
    }

    /**
     * @return array
     */
    public static function getEncoderMap()
    {
        return [
            'pgsql' => 'spinitron\dynamicAr\encoder\PgsqlEncoder',
            'mysqli' => 'spinitron\dynamicAr\encoder\MariaEncoder',
            'mysql' => 'spinitron\dynamicAr\encoder\MariaEncoder',
        ];
    }


    /**
     * Specifies the name of the table column containing dynamic attributes.
     *
     * @return string Name of the table column containing dynamic column data
     * @throws \yii\base\Exception if not overriden by descendent class.
     */
    public static function dynamicColumn()
    {
        throw new \yii\base\Exception('A DynamicActiveRecord class must override "dynamicColumn()"');
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        return $this->getAttribute($name);
    }

    /**
     * @inheritdoc
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $this->setAttribute($name, $value);
    }

    /**
     * @inheritdoc
     *
     * @param string $name
     */
    public function __isset($name)
    {
        return $this->issetAttribute($name);
    }

    /**
     * @inheritdoc
     *
     * @param string $name
     */
    public function __unset($name)
    {
        $this->unsetAttribute($name);
    }

    /**
     * Returns a model attribute value.
     *
     * @param string $name attribute name, use dotted notation for structured attributes.
     *
     * @return mixed|null the attribute value or null if the attribute does not exist
     */
    public function getAttribute($name)
    {
        try {
            return parent::__get($name);
        } catch (UnknownPropertyException $ignore) {
        }

        $path = explode('.', $name);
        $ref = &$this->_dynamicAttributes;

        foreach ($path as $key) {
            if (!isset($ref[$key])) {
                return null;
            }
            $ref = &$ref[$key];
        }

        return $ref;
    }

    /**
     * Sets a model attribute.
     *
     * @param string $name attribute name, use dotted notation for structured attributes.
     * @param mixed $value the attribute value. A value of null effectively unsets the attribute.
     */
    public function setAttribute($name, $value)
    {
        try {
            parent::__set($name, $value);

            return;
        } catch (UnknownPropertyException $ignore) {
        }

        $path = explode('.', $name);
        $ref = &$this->_dynamicAttributes;

        // Walk forwards through $path to find the deepest key already set.
        do {
            $key = $path[0];
            if (isset($ref[$key])) {
                $ref = &$ref[$key];
                array_shift($path);
            } else {
                break;
            }
        } while ($path);

        // If the whole path already existed then we can just set it.
        if (!$path) {
            $ref = $value;

            return;
        }

        // There is remaining path so we have to set a new leaf with the first
        // part of the remaining path as key. But first, if there is any path
        // beyond that then we need build an array to set as the new leaf value.
        while (count($path) > 1) {
            $key = array_pop($path);
            $value = [$key => $value];
        }
        $ref[$path[0]] = $value;
    }

    /**
     * Returns if a model attribute is set.
     *
     * @param string $name attribute name, use dotted notation for structured attributes.
     *
     * @return bool true if the attribute is set
     */
    public function issetAttribute($name)
    {
        try {
            if (parent::__get($name) !== null) {
                return true;
            }
        } catch (Exception $ignore) {
        }

        $path = explode('.', $name);
        $ref = &$this->_dynamicAttributes;

        foreach ($path as $key) {
            if (!isset($ref[$key])) {
                return false;
            }
            $ref = &$ref[$key];
        }

        return true;
    }

    /**
     * Unset a model attribute.
     *
     * @param string $name attribute name, use dotted notation for structured attributes.
     */
    public function unsetAttribute($name)
    {
        try {
            parent::__unset($name);
        } catch (\Exception $ignore) {
        }

        if ($this->issetAttribute($name)) {
            $this->setAttribute($name, null);
        }
    }

    /**
     * @inheritdoc
     */
    public function fields()
    {
        $fields = array_keys((array) $this->_dynamicAttributes);

        return array_merge(parent::fields(), $fields);
    }

    /**
     * Convert a nested array to a map of dot-notation keys to values.
     *
     * @param string $prefix Prefix returned array keys with this string
     * @param array $array Nested array of attributeName => value pairs
     *
     * @return array Map of keys in dotted notation to corresponding values
     */
    protected static function dotKeyValues($prefix, $array)
    {
        $fields = [];
        foreach ($array as $key => $value) {
            if (is_string($key)) {
                $newPos = $prefix . '.' . $key;
                if (is_array($value)) {
                    $fields = array_merge($fields, static::dotKeyValues($newPos, $value));
                } else {
                    $fields[$newPos] = $value;
                }
            }
        }

        return $fields;
    }

    /**
     * Return a list of all model attribute names recursing structured dynamic attributes.
     *
     * @return array an array of all attribute names in dotted notation
     * @throws Exception
     */
    public function dotAttributeNames()
    {
        return array_merge(
            array_values(parent::fields()),
            array_keys(static::dotKeyValues(static::dynamicColumn(), $this->_dynamicAttributes))
        );
    }

    /**
     * Return a list of all attribute values with keys in dotted notation.
     *
     * @return array Array of attribute values with attribute names as array keys in dotted notation.
     * @throws Exception
     */
    public function dotAttributes()
    {
        return array_merge(
            $this->attributes,
            static::dotKeyValues(static::dynamicColumn(), $this->_dynamicAttributes)
        );
    }

    /**
     * Generate an SQL expression referring to the given dynamic column.
     *
     * @param string $name Attribute name
     * @param string $type SQL datatype type
     *
     * @return string a SQL expression
     */
    public static function columnExpression($name, $type = 'char')
    {
        return static::getDynamicEncoder()->columnExpression($name, $type);
    }

    /**
     * Returns a query object for the model/class.
     *
     * @return DynamicActiveQuery
     */
    public static function find()
    {
        return Yii::createObject(DynamicActiveQuery::className(), [get_called_class()]);
    }

    /**
     * @param bool $insert
     *
     * @return bool
     * @throws Exception
     */
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->setAttribute(static::dynamicColumn(), static::getDynamicEncoder()->encodeDynamicColumn($this->_dynamicAttributes));

        return true;
    }

    /**
     * @param DynamicActiveRecord $record
     * @param array $row
     *
     * @throws Exception
     */
    public static function populateRecord($record, $row)
    {
        $dynCol = static::dynamicColumn();
        if (isset($row[$dynCol])) {
            $record->_dynamicAttributes = static::getDynamicEncoder()->decodeDynamicColumn($row[$dynCol]);
        }
        parent::populateRecord($record, $row);
    }

    /**
     * @inheritdoc
     */
    public function refresh()
    {
        if (!parent::refresh()) {
            return false;
        }

        $dynCol = static::dynamicColumn();
        if (isset($this->attributes[$dynCol])) {
            $this->_dynamicAttributes = static::getDynamicEncoder()->decodeDynamicColumn($this->attributes[$dynCol]);
        }

        return true;
    }
}
