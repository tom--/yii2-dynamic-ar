<?php
/**
 * Created by PhpStorm.
 * User: jwas
 * Date: 03.09.15
 * Time: 18:55
 */

namespace spinitron\dynamicAr\encoder;


interface EncoderInterface
{
    /**
     * Generate an SQL expression referring to the given dynamic column.
     *
     * @param string $name Attribute name
     * @param string $type SQL datatype type
     *
     * @return string a SQL expression
     */
    public function columnExpression($name, $type = 'char');

    /**
     * Creates a dynamic column SQL expression representing the given attributes.
     *
     * @param array $attrs the dynamic attributes, which may be nested
     *
     * @return null|\yii\db\Expression
     */
    public function dynColExpression($attrs);

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
    public function dynColDecode($encoded);
}