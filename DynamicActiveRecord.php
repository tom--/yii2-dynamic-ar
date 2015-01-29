<?php

namespace spinitron\dynamicAr;

use Yii;
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

    public static function find()
    {
        return Yii::createObject(DynamicActiveQuery::className(), [get_called_class()]);
    }

    /**
     * Create the SQL and parameter bindings for setting attributes as dynamic fields in a DB record.
     *
     * @param array $attrs Name and value pairs of dynamic fields to be saved in DB
     * @param array $params Expression parameters for binding, passed by reference
     * @param string $prefix Base parameter placeholder prefix.
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
        $sql = static::dynColSqlMaria((array) $attrs, $params);

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
        $decoded = json_decode($encoded, true);
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
