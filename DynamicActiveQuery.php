<?php
/**
 * @link https://github.com/tom--/dynamic-ar
 * @copyright Copyright (c) 2015 Spinitron LLC
 * @license http://opensource.org/licenses/ISC
 */

namespace spinitron\dynamicAr;

use Yii;
use yii\base\UnknownPropertyException;
use yii\db\ActiveQuery;

/**
 * DynamicActiveQuery represents queries on relational data with structured dynamic attributes.
 *
 * DynamicActiveQuery adds to [[ActiveQuery]] a way to write queries that involve
 * the dynamic attributes of DynamicAccessRecord models. This is only possible on
 * a DBMS that supports querying elements in serialized data structures.
 *
 * > NOTE: In this version of Dynamic AR only Maria 10.0+ is supported in this version.
 *
 * Dynamic attribtes names must be enclosed in `(! … !)` (bang-parens) and child attributes in
 * structured dynamic attributes are accessed using dotted notation, for example
 *
 * ```php
 * $model = Product::find()->where(['(!specs.color!)' => 'blue']);
 * ```
 *
 * If there is any need to specify the SQL data type of the dynamic attribute in the query,
 * for example if it appears in an SQL expression that needs this, then the type and dimension
 * can be given in the bang-parents after a `|` (vertical bar or pipe character)
 * following the attribute name, e.g.
 *
 * ```php
 * $model = Product::find()->where(['(! price.unit|DECIMAL(9,2) !) = 14.49']);
 * ```
 *
 * Allowed datatypes are specified in
 * [Maria documentation](https://mariadb.com/kb/en/mariadb/dynamic-columns/#datatypes)
 *
 * Whitespace inside the bang-parens is allowed but not around the vertical bar.
 *
 * @author Tom Worster <fsb@thefsb.org>
 * @author Danil Zakablukovskii danil.kabluk@gmail.com
 */
class DynamicActiveQuery extends ActiveQuery
{
    /**
     * @var string name of the DynamicActiveRecord's column storing serialized dynamic attributes
     */
    private $_dynamicColumn;

    /**
     * Converts the indexBy column name an anonymous function that writes rows to the
     * result array indexed an attribute name that may be in dotted notation.
     *
     * @param callable|string $column name of the column by which the query results should be indexed
     * @return $this
     */
    public function indexBy($column)
    {
        if (!$this->asArray) {
            return parent::indexBy($column);
        }

        /** @var DynamicActiveRecord $modelClass */
        $modelClass = $this->modelClass;
        $this->indexBy = function ($row) use ($column, $modelClass) {
            if (isset($row[$column])) {
                return $row[$column];
            }

            $dynamicColumn = $modelClass::dynamicColumn();
            if (!isset($row[$dynamicColumn])) {
                throw new UnknownPropertyException("Dynamic column {$dynamicColumn} does not exist - wasn't set in select");
            }

            $dynamicAttributes = $modelClass::getDynamicEncoder()->dynColDecode($row[$dynamicColumn]);
            $value = $this->getDotNotatedValue($dynamicAttributes, $column);

            return $value;
        };

        return $this;
    }

    /**
     * Maria-specific preparation for building a query that includes a dynamic column.
     *
     * @param \yii\db\QueryBuilder $builder
     *
     * @return \yii\db\Query
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function prepare($builder)
    {
        /** @var DynamicActiveRecord $modelClass */
        $modelClass = $this->modelClass;
        $this->_dynamicColumn = $modelClass::dynamicColumn();

        if (empty($this->_dynamicColumn)) {
            /** @var string $modelClass */
            throw new \yii\base\InvalidConfigException(
                $modelClass . '::dynamicColumn() must return an attribute name'
            );
        }

        if (empty($this->select)) {
            $this->select[] = '*';
        }

        if (is_array($this->select) && in_array('*', $this->select)) {
            $db = $modelClass::getDb();
            $this->select[$this->_dynamicColumn] =
                'COLUMN_JSON(' . $db->quoteColumnName($this->_dynamicColumn) . ')';
        }

        return parent::prepare($builder);
    }

    /**
     * Generate DB command from ActiveQuery with Maria-specific SQL for dynamic columns.
     *
     * User of DynamicActiveQuery should not normally need to use this method.
     *
     * #### History
     *
     * This implementation is the best I could manage. A dynamic attribute name
     * can appear anywhere that a schema attribute name could appear (select, join, where, ...).
     * It needs to be converted to the Maria SQL using COLUMN_CREATE('name', value, …)
     * for accessing dynamic columns.
     * Because SQL is statically-typed and there is no schema to refer to for dynamic
     * attributes, the accessor SQL must specify the the dyn-col's type, e.g.
     *
     * ```sql
     * WHERE COLUMN_GET(details, 'color' AS CHAR) = 'black'
     * ```
     *
     * In which details is the blob column containing all the dynamic columns, 'color' is the
     * name of a dynamic column that may or may not appear in any given table record, and
     * CHAR means the value should be cast to CHAR before it is compared with 'black'.
     * `COLUMN_GET(details, 'color' AS CHAR)` is the "accessor SQL".
     *
     * So I faced two problems:
     *
     * 1. How to identify a dynamic attribute name in an ActiveQuery?
     * 2. How to choose the type to which it should be cast in the SQL?
     *
     * The design prociple of DynamicAR is "an attribute that isn't an instance variable,
     * a column and doesn't have a magic get-/setter is assumed to be a dynamic attribute".
     * So, in order to infer from the properties of an AQ instance the attribute names
     * that need to be converted to dynamic column accessor SQL, I need to go through
     * the AQ to identify
     * all the column names and remove those in the schema. But I don't know how to
     * identify column names in an AQ instance. Even if I did, there's problem 2.
     *
     * The only way I can imagine to infer datatype from an AQ instance is to look
     * at the context. If the attribute is compared with a bound parameter, that's a clue.
     * If it is being used in an SQL function, e.g. CONCAT(), or being compared with a
     * schema column, that suggests something. But if it is on its own in a SELECT then I am
     * stuck. Also stuck if it is compared with another dynamic attribute. This seems
     * fundamentally intractible.
     *
     * So I decided that the user needs to help DynamicActiveQuery by distinguishing the names
     * of dynamic attributes and by explicitly specifying the type. The format for this:
     *
     *         (!name|type!)
     *
     * Omitting type implies the default type: CHAR. Children of dynamic attributes, i.e.
     * array elements, are separated from parents with `.` (period), e.g.
     * `(!address.country|CHAR!)`. (Spaces are not alowed around the `|`.) So a user can do:
     *
     *     $blueShirts = Product::find()
     *         ->where(['category' => Product::SHIRT, '(!color!)' => 'blue'])
     *         ->all();
     *
     *     $cheapShirts = Product::find()
     *         ->select(
     *             ['sale' => 'MAX((!cost|decimal(6,2)!), 0.75 * (!price.wholesale.12|decimal(6,2)!))']
     *         )
     *         ->where(['category' => Product::SHIRT])
     *         ->andWhere('(!price.retail.unit|decimal(6,2)!) < 20.00')
     *         ->all();
     *
     * The implementation is like db\Connection's quoting of [[string]] and {{string}}. Once
     * the full SQL string is ready, `preg_repalce()` it. The regex pattern is a bit complex
     * and the replacement callback isn't pretty either. Is there a better way to add to
     * `$params` in the callback than this? And for the parameter placeholder counter `$i`?
     *
     * @param null|\yii\db\Connection $db The database connection
     *
     * @return \yii\db\Command the modified SQL statement
     */
    public function createCommand($db = null)
    {
        /** @var DynamicActiveRecord $modelClass */
        $modelClass = $this->modelClass;
        if ($db === null) {
            $db = $modelClass::getDb();
        }

        if ($this->sql === null) {
            list ($sql, $params) = $db->getQueryBuilder()->build($this);
        } else {
            $sql = $this->sql;
            $params = $this->params;
        }

        $dynamicColumn = $modelClass::dynamicColumn();
        $callback = function ($matches) use (&$params, $dynamicColumn, $modelClass) {
            $type = !empty($matches[3]) ? $matches[3] : 'CHAR';
            $sql = $dynamicColumn;
            foreach (explode('.', $matches[2]) as $column) {
                $placeholder = $modelClass::getDynamicEncoder()->placeholder();
                $params[$placeholder] = $column;
                $sql = "COLUMN_GET($sql, $placeholder AS $type)";
            }

            return $sql;
        };

        $pattern = <<<'REGEXP'
            % (`?) \(! \s*?
                ( [a-z_\x7f-\xff][a-z0-9_\x7f-\xff]* (?: \. [^.|\s]+)* )
                (?:  \| (binary (?:\(\d+\))? | char (?:\(\d+\))? | time (?:\(\d+\))? | datetime (?:\(\d+\))? | date
                        | decimal (?:\(\d\d?(?:,\d\d?)?\))?  | double (?:\(\d\d?,\d\d?\))?
                        | int(eger)? | (?:un)? signed (?:\s+int(eger)?)?)  )?
            \s*? !\) \1 %ix
REGEXP;
        $sql = preg_replace_callback($pattern, $callback, $sql);

        return $db->createCommand($sql, $params);
    }

    /**
     * Returns the value of the element in an array refereced by a dot-notated attribute name.
     *
     * @param array $array an array of attributes and values, possibly nested
     * @param string $attribute the attribute name in dotted notation
     *
     * @return mixed|null the element in $array referenced by $attribute or null if no such
     * element exists
     */
    protected function getDotNotatedValue($array, $attribute)
    {
        $pieces = explode('.', $attribute);
        foreach ($pieces as $piece) {
            if (!is_array($array) || !array_key_exists($piece, $array)) {
                return null;
            }
            $array = $array[$piece];
        }

        return $array;
    }
}
