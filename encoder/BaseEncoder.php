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
}