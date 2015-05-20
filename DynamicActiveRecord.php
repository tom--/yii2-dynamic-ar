<?php

namespace spinitron\dynamicAr;

use Yii;
use yii\base\Exception;
use yii\base\InvalidCallException;
use yii\base\UnknownPropertyException;
use yii\db\ActiveRecord;

/**
 * Class DynamicActiveRecord
 *
 * DynamicActiveRecord adds structured dynamic attributes to Yii 2.0 ActiveRecord
 * stored, when available, in Maria 10.0+ **Dynamic Columns**, PostgreSQL 9.4+
 * **jsonb** columns, or otherwise in plain JSON, providing something like a NoSQL
 * document store within SQL relational DB tables.
 *
 * If the DBMS supports using the dynamic attributes in queries then
 * DynamicActiveRecord can combines with DynamicActiveQuery to provide an abstract
 * interface for that purpose.
 *
 * See the README of yii2-dynamic-ar extension for full description.
 *
 * @package app\db
 */
class DynamicActiveRecord extends ActiveRecord
{
    const PARAM_PREFIX = ':dqp';

    private $dynamicAttributes = [];
    const DATA_URI_PREFIX = 'data:application/octet-stream;base64,';
    /**
     * @var int
     */
    private static $placeholderCounter;

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
     * @param $value
     *
     * @return string
     */
    public static function encodeForMaria($value)
    {
        return is_string($value)
        && (!mb_check_encoding($value, 'UTF-8') || strpos($value, self::DATA_URI_PREFIX) === 0)
            ? self::DATA_URI_PREFIX . base64_encode($value)
            : $value;
    }

    /**
     * Decode strings encoded as data URIs
     *
     * @param $value
     *
     * @return string
     */
    public static function decodeForMaria($value)
    {
        return is_string($value) && strpos($value, self::DATA_URI_PREFIX) === 0
            ? file_get_contents($value)
            : $value;
    }

    /**
     * Replacement for PHP's array walk and map builtins.
     * @param $array
     * @param $method
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
     * Encodes as data URIs any "binary' strings in an array.
     * @param $array
     */
    public static function encodeArrayForMaria(& $array)
    {
        self::walk($array, 'encodeForMaria');
    }

    /**
     * Encodes any data URI strings in an array.
     * @param $array
     */
    public static function decodeArrayForMaria(& $array)
    {
        self::walk($array, 'decodeForMaria');
    }

    /**
     * @inheritdoc
     */
    public static function find()
    {
        return Yii::createObject(DynamicActiveQuery::className(), [get_called_class()]);
    }

    /**
     * Create the SQL and parameter bindings for setting attributes as dynamic fields in a DB record.
     *
     * @param array $attrs Name and value pairs of dynamic fields to be saved in DB
     * @param array $params Expression parameters for binding, passed by reference
     *
     * @return string SQL for a DB Expression
     * @throws \yii\base\Exception
     */
    private static function dynColSqlMaria($attrs, & $params)
    {
        $sql = [];
        foreach ($attrs as $key => $value) {
            $phKey = static::placeholder();
            $phValue = static::placeholder();
            $sql[] = $phKey;
            $params[$phKey] = $key;
            if (is_scalar($value) || $value === null) {
                $sql[] = $phValue;
                $params[$phValue] = $value;
            } else {
                $sql[] = static::dynColSqlMaria((array) $value, $params);
            }
        }

        return 'COLUMN_CREATE(' . implode(',', $sql) . ')';
    }

    /**
     * @param $attrs
     *
     * @return null|\yii\db\Expression
     */
    public static function dynColExpression($attrs)
    {
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
     * For now the format is JSON for Maria, PgSQL and unaware DBs.
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
            '{[\x00-\x1f]}',
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
     * Different from familliar __get() in Yii because it returns null if the requested attribute doesn't exist.
     *
     * @param string $name
     *
     * @return mixed|null
     */
    public function __get($name)
    {
        $value = null;
        try {
            $value = parent::__get($name);
        } catch (UnknownPropertyException $ignore) {
            if (isset($this->dynamicAttributes) && array_key_exists($name, $this->dynamicAttributes)) {
                $value = $this->dynamicAttributes[$name];
            }
        }

        return $value;
    }

    /**
     * Sets a dynamic attribute if a property, VA or column attribute cannot be set.
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        try {
            parent::__set($name, $value);
        } catch (UnknownPropertyException $ignore) {
            if (!preg_match('{^[a-z_\x7f-\xff][a-z0-9_\x7f-\xff]*$}i', $name)) {
                throw new InvalidCallException('Invalid attribute name "' . $name . '"');
            }
            $this->dynamicAttributes[$name] = $value;
        }
    }

    /**
     * Tests any kind of property or attribute, dynamic or otherwise.
     *
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        try {
            $set = parent::__get($name) !== null;
        } catch (Exception $ignore) {
            $set = false;
        }

        if (!$set) {
            $set = isset($this->dynamicAttributes[$name]);
        }

        return $set;
    }

    /**
     * Unsets any kind of property or attribute, dynamic or otherwise.
     *
     * @param string $name
     */
    public function __unset($name)
    {
        if (array_key_exists($name, $this->dynamicAttributes)) {
            unset($this->dynamicAttributes[$name]);
        } else {
            parent::__unset($name);
        }
    }

    /**
     * @inheritdoc
     */
    public function fields()
    {
        $fields = array_keys((array) $this->dynamicAttributes);

        return array_merge(parent::fields(), $fields);
    }

    /**
     * Allows access to child attributes through dot notation.
     *
     * @param $name
     *
     * @return bool
     */
    public function issetAttribute($name)
    {
        if (strpos($name, '.') === false) {
            return isset($this->$name);
        }

        $path = explode('.', $name);
        $ref = & $this->dynamicAttributes;

        foreach ($path as $key) {
            if (!isset($ref[$key])) {
                return false;
            }
            $ref = & $ref[$key];
        }

        return true;
    }

    /**
     * Allows access to child attributes through dot notation.
     *
     * @param $name
     */
    public function unsetAttribute($name)
    {
        if (strpos($name, '.') === false) {
            unset($this->name);

            return;
        }

        $this->setAttribute($name, null);
    }

    /**
     * Allows access to child attributes through dot notation.
     *
     * @param string $name
     *
     * @return mixed|null
     */
    public function getAttribute($name)
    {
        try {
            return parent::__get($name);
        } catch (UnknownPropertyException $ignore) {
        }

        $path = explode('.', $name);
        $ref = & $this->dynamicAttributes;

        foreach ($path as $key) {
            if (!isset($ref[$key])) {
                return null;
            }
            $ref = & $ref[$key];
        }

        return $ref;
    }

    /**
     * @param string $name Dot notation signifies position in an array in
     * a dynamic attribute, for example
     *
     *     $model->setAttribute('car.owner.name.last', 'Smith')
     *
     * is like
     *
     *     $model->car['owner']['name']['last'] = 'Smith'
     *
     * if that were possible, if you know what I mean.
     * @param mixed $value
     */
    public function setAttribute($name, $value)
    {
        if (strpos($name, '.') === false) {
            $this->$name = $value;

            return;
        }

        $path = explode('.', $name);
        $ref = & $this->dynamicAttributes;

        // Walk forwards through $path to find the deepends key already set.
        do {
            $key = $path[0];
            if (isset($ref[$key])) {
                $ref = & $ref[$key];
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

        // If there is remaining path then we have to set a new leaf
        // in dynamicAttributes. Its key will be the first part of the
        // remaining path. If there is any path beyond that then we need
        // build an array to set it to.
        while (count($path) > 1) {
            $key = array_pop($path);
            $value = [$key => $value];
        }
        $ref[$path[0]] = $value;
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
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->setAttribute(static::dynamicColumn(), static::dynColExpression($this->dynamicAttributes));

        return true;
    }

    /**
     * @inheritdoc
     */
    public static function populateRecord($record, $row)
    {
        $dynCol = static::dynamicColumn();
        if (isset($row[$dynCol])) {
            $record->dynamicAttributes = static::dynColDecode($row[$dynCol]);
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
            $this->dynamicAttributes = static::dynColDecode($this->attributes[$dynCol]);
        }

        return true;
    }
}
