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
    /**
     * Prefix for base64 encoded dynamic attribute values
     */
    const DATA_URI_PREFIX = 'data:application/octet-stream;base64,';

    /**
     * Prefix of PDO placeholders for Dynamic Column names and values
     */
    const PARAM_PREFIX = ':dqp';

    /**
     * @var int Counter of PDO placeholders used in a query.
     */
    protected static $placeholderCounter;

    private $_dynamicAttributes = [];

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
     */
    public function __set($name, $value)
    {
        $this->setAttribute($name, $value);
    }

    /**
     * @inheritdoc
     */
    public function __isset($name)
    {
        return $this->issetAttribute($name);
    }

    /**
     * @inheritdoc
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
     * Return a list of an array's keys in dotted notation, recursing subarrays.
     *
     * @param string $prefix Prefix returned array keys with this string
     * @param array $array An array of attributeName => value pairs
     *
     * @return array The list of keys in dotted notation
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
     *
     * @return array
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
     * Returns a PDO parameter placeholder string, incrementing the placeholder counter.
     *
     * @return string the placeholder string
     */
    public static function placeholder()
    {
        if (static::$placeholderCounter === null) {
            static::$placeholderCounter = 1;
        } else {
            static::$placeholderCounter += 1;
        }

        return static::PARAM_PREFIX . static::$placeholderCounter;
    }

    /**
     * Encode as data URIs strings that JSON cannot express.
     *
     * @param mixed $value a value to encode
     *
     * @return string the encoded data URI
     */
    public static function encodeForMaria($value)
    {
        return is_string($value)
        && (!mb_check_encoding($value, 'UTF-8') || strpos($value, self::DATA_URI_PREFIX) === 0)
            ? self::DATA_URI_PREFIX . base64_encode($value)
            : $value;
    }

    /**
     * Decode strings encoded as data URIs.
     *
     * @param string $value the data URI to decode
     *
     * @return string the decoded value
     */
    public static function decodeForMaria($value)
    {
        return is_string($value) && strpos($value, self::DATA_URI_PREFIX) === 0
            ? file_get_contents($value)
            : $value;
    }

    /**
     * Replacement for PHP's array walk and map builtins.
     *
     * @param array $array An array to walk, which may be nested
     * @param callable $method A method to map on the array
     */
    protected static function walk(& $array, $method)
    {
        if (is_scalar($array)) {
            $array = static::$method($array);

            return;
        }

        $replacements = [];
        foreach ($array as $key => & $value) {
            if (is_scalar($value) || $value === null) {
                $value = static::$method($value);
            } else {
                static::walk($value, $method);
            }
            $newKey = static::$method($key);
            if ($newKey !== $key) {
                $replacements[$newKey] = $value;
                unset($array[$key]);
            }
        }
        foreach ($replacements as $key => $value2) {
            $array[$key] = $value2;
        }
    }

    /**
     * Encodes as data URIs any "binary" strings in an array.
     *
     * @param array $array the array
     */
    protected static function encodeArrayForMaria(& $array)
    {
        self::walk($array, 'encodeForMaria');
    }

    /**
     * Decodes any data URI strings in an array.
     *
     * @param array $array the array
     */
    protected static function decodeArrayForMaria(& $array)
    {
        self::walk($array, 'decodeForMaria');
    }

    /**
     * Creates the SQL and parameter bindings for setting dynamic attributes
     * in a DB record as Dynamic Columns in Maria.
     *
     * @param array $attrs the dynamic attributes, which may be nested
     * @param array $params expression parameters for binding, passed by reference
     *
     * @return string SQL for a DB Expression
     * @throws \yii\base\Exception
     */
    protected static function dynColSqlMaria(array $attrs, & $params)
    {
        $sql = [];
        foreach ($attrs as $key => $value) {
            if (is_object($value) && !($value instanceof DynamicValue)) {
                $value = method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
            }
            if ($value === [] || $value === null) {
                continue;
            }

            $phKey = static::placeholder();
            $phValue = static::placeholder();
            $sql[] = $phKey;
            $params[$phKey] = $key;

            if ($value instanceof DynamicValue || is_float($value)) {
                $sql[] = $value;
            } elseif (is_scalar($value)) {
                $sql[] = $phValue;
                $params[$phValue] = $value;
            } elseif (is_array($value)) {
                $sql[] = static::dynColSqlMaria($value, $params);
            }
        }

        return $sql === [] ? 'null' : 'COLUMN_CREATE(' . implode(',', $sql) . ')';
    }

    /**
     * Creates a dynamic column SQL expression representing the given attributes.
     *
     * @param array $attrs the dynamic attributes, which may be nested
     *
     * @return null|\yii\db\Expression
     */
    public static function dynColExpression($attrs) {
        if (!$attrs) {
            return null;
        }

        $params = [];

        // todo For now we only have Maria. Add PgSQL and generic JSON.
        static::encodeArrayForMaria($attrs);
        $sql = static::dynColSqlMaria($attrs, $params);

        return new \yii\db\Expression($sql, $params);
    }

    /**
     * Decode a serialized blob of dynamic attributes.
     *
     * At present the only supported input format is JSON returned from Maria. It may work
     * also for PostgreSQL.
     *
     * @param string $encoded Serialized array of attributes in DB-specific form
     *
     * @return array Dynamic attributes in name => value pairs (possibly nested)
     */
    public static function dynColDecode($encoded)
    {
        // Maria has a bug in its COLUMN_JSON funcion in which it fails to escape the
        // control characters U+0000 through U+001F. This causes JSON decoders to fail.
        // This workaround escapes those characters.
        $encoded = preg_replace_callback(
            '/[\x00-\x1f]/',
            function ($matches) {
                return sprintf('\u00%02x', ord($matches[0]));
            },
            $encoded
        );

        $decoded = json_decode($encoded, true);
        if ($decoded) {
            static::decodeArrayForMaria($decoded);
        }

        return $decoded;
    }

    /**
     * @inheritdoc
     */
    public static function find()
    {
        return Yii::createObject(DynamicActiveQuery::className(), [get_called_class()]);
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->setAttribute(static::dynamicColumn(), static::dynColExpression($this->_dynamicAttributes));

        return true;
    }

    /**
     * @inheritdoc
     */
    public static function populateRecord($record, $row)
    {
        $dynCol = static::dynamicColumn();
        if (isset($row[$dynCol])) {
            $record->_dynamicAttributes = static::dynColDecode($row[$dynCol]);
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
            $this->_dynamicAttributes = static::dynColDecode($this->attributes[$dynCol]);
        }

        return true;
    }
}
