<?php

namespace app\db;

use Yii;

/**
 * Class DynamicActiveRecord
 *
 * Dynamic Active Record adds to Yii 2.0 Active Record unstructured attributes
 * stored in Maria 10.0+ Dynamic Columns or PostgreSQL 9.4+ jsonb columns. It
 * provides the features of a NoSQL DB within a column in an SQL RDBMS table.
 *
 * The design assumes that there is exactly one column in a DAR class's table
 * to store the dynamic columns (dyn-cols), identified by the dynamicColumn()
 * method. The main concept in the design is that you can read or write or
 * write any attribute name and if it is not a schema column attribute or a
 * defined by a virtual attribute getter/setter then it is assumed to be the
 * name of a dyn-col.
 *
 * So, for example, if a DAR class Product represents the following Maria 10.0
 * and detail is the column containing the dynamic columns...
 *
 * ```sql
 * CREATE TABLE product (
 *    id int(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *    name varchar(100) NOT NULL,
 *    sku char(10) NOT NULL,
 *    details blob NOT NULL)
 * ```
 *
 * ```php
 * $shirt = new Product();
 * $shirt->name = 'Men\s' Tee Shirt';
 * $shirt->sku = 'STMA123456';
 * $shirt->color = 'black';
 * $shirt->size = 'L';
 * $shirt->fabric = 'cotton';
 * ```
 *
 * The name and sku are stored in normal columns identified in the schema. DAR
 * recognizes that the other attributes, color, size and fabric, are not in the
 * schema and saves them as dyn-cols instead.
 *
 * Note: DAR does not (at present) prevent writing to the blob column containing
 * dynamic
 *
 * A dyn-col can contain structued data. At the top level they are accessed as
 * though they were object properties, just like AR attributes. But the value
 * may be a PHP array.
 *
 * ```php
 * $shirt->price = [
 *     'retail' => 12.99,
 *     'wholesale' => [12 => 10.50, 60 => 9.50]
 * ];
 * ```
 *
 * After loading a DAR record, all its dyn-cols are available for reading.
 *
 * @package app\db
 */
abstract class DynamicActiveRecord extends \yii\db\ActiveRecord
{
    const PARAM_PREFIX_ATTRS = ':dca';
    const PARAM_PREFIX_QUERY = ':dcq';

    private $_dynamicAttributes = [];

    public static function find()
    {
        return Yii::createObject(DynamicActiveQuery::className(), [get_called_class()]);
    }

    /**
     * Maria-specific dynamic column blob decoding.
     *
     * @param string $encoded
     *
     * @return array mixed
     */
    public static function dynColDecodeMaria($encoded)
    {
        return json_decode($encoded, true);
    }

    /**
     * Decode a dynamic attribute blob.
     *
     * @param string $encoded Serialized array of attributes in DB-specific form
     * @return array Dynamic attributes in name => value pairs (possibly nested)
     */
    public static function dynColDecode($encoded)
    {
        return static::dynColDecodeMaria($encoded);
    }

    /**
     * Maria-specific SQL for writing dynamic column fields
     *
     * @param array $attrs
     * @param array $params
     * @param string $prefix
     *
     * @return string
     */
    public static function dynColSqlMaria($attrs, &$params, $prefix)
    {
        $sql = [];
        $i = 0;
        foreach ($attrs as $key => $value) {
            $i += 1;
            $ph = $prefix . $i;
            $sql[] = $ph . 'k';
            $params[$ph . 'k'] = $key;
            if (is_scalar($value)) {
                $sql[] = $ph . 'v';
                $params[$ph . 'v'] = $value;
            } else {
                $ph .= '_';
                $sql[] = self::dynColSql((array) $value, $params, $ph);
            }
        }
        return 'COLUMN_CREATE(' . implode(',', $sql) . ')';
    }

    /**
     * Create the SQL and parameter bindings for setting attributes as dynamic fields in a DB record.
     *
     * @param array $attrs Name and value pairs of dynamic fields to be saved in DB
     * @param array $params Expression parameters for binding, passed by reference
     * @param string $prefix Base parameter placeholder prefix.
     * @return string SQL for a DB Expression
     * @throws \yii\base\Exception
     */
    public static function dynColSql($attrs, &$params, $prefix)
    {
        return static::dynColSqlMaria($attrs, $params, self::PARAM_PREFIX_ATTRS);
    }

    public static function dynColExpression($attrs)
    {
        if (!$attrs) {
            return null;
        }

        $params = [];
        $sql = static::dynColSql($attrs, $params, self::PARAM_PREFIX_ATTRS);
        return new \yii\db\Expression($sql, $params);
    }

    public function __get($name)
    {
        if (isset($this->_dynamicAttributes) && array_key_exists($name, $this->_dynamicAttributes)) {
            return $this->_dynamicAttributes[$name];
        }

        return parent::__get($name);
    }

    public function __set($name, $value)
    {
        try {
            parent::__set($name, $value);
        } catch (\yii\base\UnknownPropertyException $e) {
            $this->_dynamicAttributes[$name] = $value;
        }
    }

    public function __isset($name)
    {
        return isset($this->_dynamicAttributes[$name]) || parent::__isset($name);
    }

    public function __unset($name)
    {
        if (array_key_exists($name, $this->_dynamicAttributes)) {
            unset($this->_dynamicAttributes[$name]);
        } else {
            parent::__unset($name);
        }
    }

    public function fields()
    {
        die('dead!');
        $fields = array_keys((array) $this->_dynamicAttributes);
        return array_merge($fields, parent::fields());
    }

    public static function dynamicColumn()
    {
        throw new \yii\base\Exception('A DynamicActiveRecord class must implement the "dynamicColumn" method');
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            $this->setAttribute(static::dynamicColumn(), self::dynColExpression($this->_dynamicAttributes));
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
            $record->_dynamicAttributes = static::dynColDecode($row[$dynCol]);
        }
        parent::populateRecord($record, $row);
    }
}
