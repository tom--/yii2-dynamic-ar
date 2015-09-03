<?php
/**
 * @link https://github.com/tom--/dynamic-ar
 * @copyright Copyright (c) 2015 Spinitron LLC
 * @license http://opensource.org/licenses/ISC
 */

namespace spinitron\dynamicAr\encoder;

use spinitron\dynamicAr\ValueExpression;

class PgsqlEncoder extends BaseEncoder
{
    /**
     * Generate an SQL expression referring to the given dynamic column.
     *
     * @param string $name Attribute name
     * @param string $type SQL datatype type
     *
     * @return string a SQL expression
     */
    public function columnExpression($name, $type = 'char')
    {
        $modelClass = $this->modelClass;
        $sql = '[[' . $modelClass::dynamicColumn() . ']]';
        foreach (explode('.', $name) as $column) {
            $sql = "($sql->'$column' AS $type)";
        }

        return $sql;
    }

    /**
     * Creates a dynamic column SQL expression representing the given attributes.
     *
     * @param array $attributes the dynamic attributes, which may be nested
     *
     * @return null|\yii\db\Expression
     */
    public function encodeDynamicColumn($attributes) {
        if (!$attributes) {
            return null;
        }

        $params = [];

        // todo For now we only have Maria. Add PgSQL and generic JSON.
        static::encodeDynamicAttributeArray($attributes);
        $sql = static::dynColSqlMaria($attributes, $params);

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
    public function decodeDynamicColumn($encoded)
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
            static::decodeDynamicAttributeArray($decoded);
        }

        return $decoded;
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
    private static function dynColSqlMaria(array $attrs, & $params)
    {
        $sql = [];
        foreach ($attrs as $key => $value) {
            if (is_object($value) && !($value instanceof ValueExpression)) {
                $value = method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
            }
            if ($value === [] || $value === null) {
                continue;
            }

            $phKey = static::placeholder();
            $phValue = static::placeholder();
            $sql[] = $phKey;
            $params[$phKey] = $key;

            if ($value instanceof ValueExpression || is_float($value)) {
                $sql[] = $value;
            } elseif (is_scalar($value)) {
                $sql[] = $phValue;
                $params[$phValue] = $value;
            } elseif (is_array($value)) {
                $sql[] = static::dynColSqlMaria($value, $params);
            }
        }

        return $sql === [] ? 'null' : 'json_build_object(' . implode(',', $sql) . ')';
    }
}