<?php
/**
 * @link https://github.com/tom--/dynamic-ar
 * @copyright Copyright (c) 2015 Spinitron LLC
 * @license http://opensource.org/licenses/ISC
 */

namespace spinitron\dynamicAr\encoder;


use spinitron\dynamicAr\DynamicActiveRecord;

abstract class BaseEncoder extends \yii\base\Object implements EncoderInterface
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
     * @var DynamicActiveRecord name of the model that uses this encoder.
     */
    public $modelClass;

    /**
     * @var int Counter of PDO placeholders used in a query.
     */
    protected static $placeholderCounter;

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
    protected static function encodeDynamicAttribute($value)
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
    protected static function decodeDynamicAttribute($value)
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
    protected static function encodeDynamicAttributeArray(& $array)
    {
        self::walk($array, 'encodeDynamicAttribute');
    }

    /**
     * Decodes any data URI strings in an array.
     *
     * @param array $array the array
     */
    protected static function decodeDynamicAttributeArray(& $array)
    {
        self::walk($array, 'decodeDynamicAttribute');
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