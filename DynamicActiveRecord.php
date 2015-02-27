<?php

namespace spinitron\dynamicAr;

use tests\unit\DynamicActiveRecordTest;
use Yii;
use yii\base\Exception;
use yii\base\InvalidCallException;
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
abstract class DynamicActiveRecord extends ActiveRecord
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
        if (self::$placeholderCounter === null) {
            self::$placeholderCounter = 1;
        } else {
            self::$placeholderCounter += 1;
        }
        return self::PARAM_PREFIX . self::$placeholderCounter;
    }

    public static function encodeForMaria($value)
    {
        return is_string($value)
        && (!mb_check_encoding($value, 'UTF-8') || strpos($value, self::DATA_URI_PREFIX) === 0)
            ? self::DATA_URI_PREFIX . base64_encode($value)
            : $value;
    }

    public static function decodeForMaria($value)
    {
        return is_string($value) && strpos($value, self::DATA_URI_PREFIX) === 0
            ? file_get_contents($value)
            : $value;
    }

    public static function walk(&$array, $method)
    {
        if (is_scalar($array)) {
            $array = self::$method($array);
            return;
        }

        $replacements = [];
        foreach ($array as $key => &$value) {
            if (is_scalar($value)) {
                $value = self::$method($value);
            } else {
                self::walk($value, $method);
            }
            $newKey = self::$method($key);
            if ($newKey !== $key) {
                $replacements[$newKey] = $value;
                unset($array[$key]);
            }
        }
        foreach ($replacements as $key => $value2) {
            $array[$key] = $value2;
        }
    }

    public static function encodeArrayForMaria(&$array)
    {
        self::walk($array, 'encodeForMaria');
    }

    public static function decodeArrayForMaria(&$array)
    {
        self::walk($array, 'decodeForMaria');
    }

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
    private static function dynColSqlMaria($attrs, &$params)
    {
        $sql = [];
        foreach ($attrs as $key => $value) {
            $phKey = self::placeholder();
            $phValue = self::placeholder();
            $sql[] = $phKey;
            $params[$phKey] = $key;
            if (is_scalar($value)) {
                $sql[] = $phValue;
                $params[$phValue] = $value;
            } else {
                $sql[] = self::dynColSqlMaria((array) $value, $params);
            }
        }
        return 'COLUMN_CREATE(' . implode(',', $sql) . ')';
    }

    public static function dynColExpression($attrs)
    {
        if (!$attrs) {
            return null;
        }

        $params = [];

        // todo For now we only have Maria. Add PgSQL and generic JSON.
        self::encodeArrayForMaria($attrs);
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
        self::decodeArrayForMaria($decoded);

        return $decoded;
    }

    public function __get($name)
    {
        $value = null;
        try {
            $value = parent::__get($name);
        } catch (\yii\base\UnknownPropertyException $e) {
            if (isset($this->dynamicAttributes) && array_key_exists($name, $this->dynamicAttributes)) {
                $value = $this->dynamicAttributes[$name];
            }
        }

        return $value;
    }

    public function __set($name, $value)
    {
        try {
            parent::__set($name, $value);
        } catch (\yii\base\UnknownPropertyException $e) {
            if (!preg_match('{^[a-z_\x7f-\xff][a-z0-9_\x7f-\xff]*$}i', $name)) {
                throw new InvalidCallException('Invalid attribute name "' . $name . '"');
            }
            $this->dynamicAttributes[$name] = $value;
        }
    }

    public function __isset($name)
    {
        return isset($this->dynamicAttributes[$name]) || parent::__isset($name);
    }

    public function __unset($name)
    {
        if (array_key_exists($name, $this->dynamicAttributes)) {
            unset($this->dynamicAttributes[$name]);
        } else {
            parent::__unset($name);
        }
    }

    public function fields()
    {
        $fields = array_keys((array) $this->dynamicAttributes);
        return array_merge(parent::fields(), $fields);
    }

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
        }

        return true;
    }

    public function unsetAttribute($name)
    {
        if (strpos($name, '.') === false) {
            unset($this->name);
            return;
        }

        $this->setAttribute($name, null);
    }

    /**
     * @inheritdoc
     */
    public function getAttribute($name)
    {
        if (strpos($name, '.') === false) {
            return $this->$name;
        }

        $path = explode('.', $name);
        $ref = & $this->dynamicAttributes;

        foreach ($path as $key) {
            if (!isset($ref[$key])) {
                trigger_error('Undefined attribute ' . $name);
                return null;
            }
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
     * @return string Name of the table column containing dynamic column data
     * @throws \yii\base\Exception
     */
    public static function dynamicColumn()
    {
        throw new \yii\base\Exception('A DynamicActiveRecord class must override "dynamicColumn()"');
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            $this->setAttribute(static::dynamicColumn(), self::dynColExpression($this->dynamicAttributes));
            return true;
        }
        return false;
    }

    /**
     * @param DynamicActiveRecord $record
     * @param array $row
     */
    public static function populateRecord($record, $row)
    {
        $dynCol = static::dynamicColumn();
        if (isset($row[$dynCol])) {
            $record->dynamicAttributes = static::dynColDecode($row[$dynCol]);
        }
        parent::populateRecord($record, $row);
    }
}
