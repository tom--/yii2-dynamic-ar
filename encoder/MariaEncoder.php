<?php
/**
 * @link https://github.com/tom--/dynamic-ar
 * @copyright Copyright (c) 2015 Spinitron LLC
 * @license http://opensource.org/licenses/ISC
 */

namespace spinitron\dynamicAr\encoder;

use spinitron\dynamicAr\ValueExpression;

class MariaEncoder extends BaseEncoder
{
    /**
     * Generate an SQL expression referring to the given dynamic column.
     *
     * @param string $name Attribute name
     * @param string $type SQL datatype type
     *
     * @return string a Maria COLUMN_GET expression
     */
    public function columnExpression($name, $type = 'char')
    {
        $modelClass = $this->modelClass;
        $sql = '[[' . $modelClass::dynamicColumn() . ']]';
        foreach (explode('.', $name) as $column) {
            $sql = "COLUMN_GET($sql, '$column' AS $type)";
        }

        return $sql;
    }

    /**
     * Creates a dynamic column SQL expression representing the given attributes.
     *
     * @param array $attrs the dynamic attributes, which may be nested
     *
     * @return null|\yii\db\Expression
     */
    public function dynColExpression($attrs) {
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
    public function dynColDecode($encoded)
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
     * Encodes as data URIs any "binary" strings in an array.
     *
     * @param array $array the array
     */
    private static function encodeArrayForMaria(& $array)
    {
        self::walk($array, 'encodeForMaria');
    }

    /**
     * Decodes any data URI strings in an array.
     *
     * @param array $array the array
     */
    private static function decodeArrayForMaria(& $array)
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

        return $sql === [] ? 'null' : 'COLUMN_CREATE(' . implode(',', $sql) . ')';
    }

    /**
     * Replacement for PHP's array walk and map builtins.
     *
     * @param array $array An array to walk, which may be nested
     * @param callable $method A method to map on the array
     */
    private static function walk(& $array, $method)
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
}