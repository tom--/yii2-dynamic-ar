<?php

namespace app\db;

use Yii;
use yii\base\Component;

/**
 * Dynamic columns can be used queries but DAQ requires a special syntax to identify
 * that you are refering to a dynamic column. Owing to SQL's static typing you must
 * also specify the type in the query. See createCommand() for specifics.
 *
 * Class DynamicActiveQuery
 *
 * @package app\db
 */
class DynamicActiveQuery extends \yii\db\ActiveQuery
{
    /**
     * Maria-specific preparation for building a query that includes a dynamic column.
     *
     * @param \yii\db\QueryBuilder $builder
     *
     * @return \yii\db\Query
     */
    public function prepare($builder)
    {
        /** @var DynamicActiveRecord $modelClass */
        $modelClass = $this->modelClass;
        $dynCol = $modelClass::dynamicColumn();
        if (!empty($dynCol)) {
            if (empty($this->select)) {
                $attrNames = array_keys($modelClass::getTableSchema()->columns);
                $this->select = array_diff($attrNames, [$dynCol]);
            }
            $this->select[$dynCol] = 'COLUMN_JSON(' . $dynCol . ')';
        }

        return parent::prepare($builder);
    }

    /**
     * Generate DB command from ActiveQuery with Maria-specific SQL for dynamic columns.
     *
     * This implementation is the best hack I could manage. A dynamic column name
     * can appear anywhere that a normal column name could appear (select, join, where, ...).
     * It needs to be converted to the Maria SQL for accessing a dynamic column.
     * Because SQL is statically-typed and there is no schema to refer to for the dyn-cols,
     * the accessor SQL must specify the the dyn-col's type, e.g.
     *
     *     WHERE COLUMN_GET(details, 'color' as char) = 'black'
     *
     * In which details is the blob column containing all the dynamic columns, 'color' is the
     * name of a dynamic column that may or may not appear in any given table record, and
     * char means the value should be cast to char before it is compared with 'black'
     *
     * So I faced two problems:
     *    1. How to identify a dynamic column name in an ActiveQuery?
     *    2. How to choose the type to which it should be cast?
     *
     * The operating and design concept of DynamicAR is "a column that doesn't appear in the
     * schema is assumed to be a dynamic column". So to infer from an AQ instance the column names
     * that need to be converted to dyn-col accessor SQL I need to go through the AQ to identify
     * all the column names and remove those in the schema. But I don't know how to
     * identify column names in an AQ instance. Even if I did, there's problem 2.
     *
     * The only way I can imagine to infer dynamic column type from an AQ instance is to look
     * at the context. If the dyn-col is compared with a bound parameter, that's a possible
     * approach. If it is being used in a function, e.g. CONCAT(), or being compared with a
     * schema column, that suggests something. But if it is on its own in a SELECT then I am
     * stuck. Also stuck if it is compared with another dyn-col. This seems fundamentally
     * intractible to me.
     *
     * So I decided that the user needs to help ActiveQuery by distinguishing names that
     * are dyn-col names and by explicitly specifying the type. The format I chose is:
     *
     *     <column|type>
     *
     * Omitting type implies the default type: char. Child dyn-col names are separated from
     * parents with . (period), e.g. <address.country|char>. Spaces are not tolerated.
     * So a user can do:
     *
     *     $blackShirts = Product::find()
     *         ->where(['category' => Product::SHIRT, '<color>' => 'black'])
     *         ->all();
     *
     *     $cheapShirts = Product::find()
     *         ->where(['category' => Product::SHIRT])
     *         ->andWhere('<price.retail.unit|decimal(6,2)> < 20.00')
     *         ->all();
     *
     * The implementation follows db\Connection's quoting of [[string]] and {{string}}. Once
     * the full SQL string is ready, preg_repalce it. The regex pattern here is a bit complex
     * and the replacement callback isn't pretty either. Is there a better way to add to
     * $params in the callback than this? And for the parameter placeholder counter $i?
     *
     * @param null|\yii\db\Connection $db
     *
     * @return \yii\db\Command
     */
    public function createCommand($db = null)
    {
        /** @var DynamicActiveRecord $modelClass */
        $modelClass = $this->modelClass;

        if ($db === null) {
            $db = $modelClass::getDb();
        }
        list ($sql, $params) = $db->getQueryBuilder()->build($this);

        $dynCol = $modelClass::dynamicColumn();

        $callback = function ($matches) use (&$params, $dynCol, &$i) {
            $type = !empty($matches[2]) ? $matches[2] : 'char';
            $sql = $dynCol;
            $parts = explode('.', $matches[1]);
            $nParts = count($parts);
            foreach ($parts as $col) {
                $i += 1;
                $placeholder = DynamicActiveRecord::PARAM_PREFIX_QUERY . $i;
                $params[$placeholder] = $col;
                $sql = "COLUMN_GET($sql, $placeholder AS " . ($i == $nParts ? $type : 'char') . ')';
            }
            return $sql;
        };

        $start = '[a-z_\x7f-\xff]';
        $cont = '[a-z0-9_\x7f-\xff]';
        $l = '(?:\(\d[\d,]*\))?';
        $type
            = "binary$l|char$l|date|datetime$l|decimal$l|double$l|int(eger)?"
            . "|signed(?: inte(eger)?)?|time$l|unsigned(?: inte(eger)?)?";
        $pattern = "{ < ($start $cont* (?: \\. $cont+)*) (?: \\| ($type))? > }ux";

        $i = 0;
        $sql = preg_replace_callback($pattern, $callback, $sql);

        return $db->createCommand($sql, $params);
    }
}
