# yii2-dynamic-ar extension

**Dynamic, structured attributes in [Active Record](http://www.yiiframework.com/doc-2.0/yii-db-activerecord.html) and [Active Query](http://www.yiiframework.com/doc-2.0/yii-db-activequery.html) for SQL tables**


## Motivation

NoSQL-like features have been appearing in some SQL relational databases, including [Dynamic Columns in Maria 10.0+](https://mariadb.com/kb/en/mariadb/dynamic-columns/)
and
[jsonb column types](http://www.postgresql.org/docs/9.4/static/datatype-json.html)
and
[functions in PostgreSQL 9.4+](http://www.postgresql.org/docs/9.4/static/functions-json.html).

These features are useful for representing entities with properties that don't comfortably map to table columns, for example because:

- the number of properties needed to describe your collection of entities is large
- individual entities are described by a small subset of the properties
- the set of properties is fluid with properties being introduced or retired through the life of the application

The classic example is
product descriptions in a vendor's database. Washing machines need to be described
with properties relevant to them (capacity, spin speed, power) while cameras
require other properties. Imagine the set of properties for a general shopping website with a wide range of product types. Now imagine that product and product types need to be changed frequently.

Such entities are easily represented in a NoSQL document store such as CouchDB or MongoDB. But until recently representing them in relational DB tables typically means choosing a method that involves difficult compromise:

- Mapping one property to one column can lead to far too many columns that are very sparsely populated and columns must be frequently added and removed (the table description could be as dynamic as the data in it!).
- Sub-classing the entities into a hierarchy of types (to the extent that the entities allow such categorization) can allow mapping the types to a schema of related tables. This can mitigate sparse column population and somewhat contain the impact of schema changes at the cost of table proliferation. You have just as many columns and records in total but fewer empty fields.
- EAV tables make anything except primitive search and querying very complex – well know problems.
- Serializing the properties into a column containing all the dynamic properties means you cannot query individual properties.

However, Maria 10 and PostgreSQL 9.4 have the ability to serialize an arbitrary set of (structured) properties while allowing them to be used in queries. This suggests the possibility of having the best of both worlds (RDBMS and document store) in one place.

The goal of yii2-dynamic-ar extension is to provide a comfortable API to these capabilities.


## Design and operation

DynamicActiveRecord extends [ActiveRecord](http://www.yiiframework.com/doc-2.0/yii-db-activerecord.html) to represent structured dynamic attributes that are stored in serialized form in the database. If the particular DBMS has features to support this then they are used, otherwise JSON is used. In the case that the DBMS allows querying of the serialized properties DynamicActiveQuery extends  [Active Query](http://www.yiiframework.com/doc-2.0/yii-db-activequery.html) to represent such queries.

### DynamicActiveRecord

In a DynamicActiveRecord object, any property you access is a dynamic attribute when:

- it isn't a normal column attribute, and
- it does not have its own [magic getter or setter](http://www.yiiframework.com/doc-2.0/guide-concept-properties.html#properties).

Thus, if `$product` is an instance of a `DynamicActiveRecord` class then

```php
$product->color = 'red';
```

will write to:

- the column attribute 'color', if the model's table has a column named 'color',
- or it will call `$product->setColor('red')`, if you declared this method,
- otherwise it will write to (either creating or updating) a dynamic attribute named 'color'.

Dynamic attributes can have array values, e.g.

```php
$product->price = [
    'retail' => 12.99,
    'wholesale' => [
        6 => 12.00,
        12 => 11.50,
        60 => 10.40,
    ],
]
```

A dynamic attribute's name must be a [valid PHP label](http://php.net/manual/en/language.variables.basics.php) but child labels can be any non-empty string.

All dynamic attributes are populated into a model object from the DB when loading a model, regardless of the query's `select` property.

When updating a model, the DB record's dynamic attributes are all overwritten. Another way to think about this is – after save, whether it is an insert or update, the DB record has the same set of dynamic attributes as the model. For example, let's say the record with PK of 7 in the `product` table has a dynamic field 'speed'. If we now save a `Product` model with `id` 7 but without a dynamic attribute 'speed' then the dynamic field 'speed' 'is deleted from row 7 in the table.

### DynamicActiveQuery

**tl;dr** When you use a dynamic attribute in a query, write it as a *dynamic attribute token*, e.g. `{color}` or `{employee.id|INT}` (see below for full details). 


DynamicActiveQuery only exists for those DMBSs that provide a way to query elements in data structures serialized to a field (for now, Maria 10+ and PostgreSQL 9.4+).

We would like to be able to use dynamic attributes in database queries with the same flexibility as we have with schema column
attributes. DynamicActiveQuery should allow, for example, creation of SQL such as the following, whether
the columns involved are serialized dynamic properties or table columns in the schema:

```sql
SELECT 
	t1.price,
	CONCAT('Compare at $', t1.price) AS foo, 
	MIN(t1.price, t1.discount) AS sale
FROM t1 JOIN t2 ON MIN(t1.price, t1.discount) > t2.cost
WHERE price * 1.33 < 50.00
ORDER BY MIN(0.66 * t1.price, t2.cost)
```

There is a challenge. From the Maria manual:

> SQL is a statically-typed language. The SQL interpreter needs to know the datatypes often
all expressions before the query is run (for example, when one is using prepared statements
and runs [a SELECT], the prepared statement API requires the server to inform the client
about the datatype of the column being read before the query is executed and the server can
see what datatype the column actually has).

So DynamicActiveQuery needs a way to:

1. identify dynamic attribute names anywhere that a column name can appear in an ActiveQuery instance
1. choose an appropriate SQL type for each dynamic attribute

The first cannot in general be automated by our extension without constraining the requirements or by radically changing ActiveQuery. Automating the second seems intractable to me. So I decided instead to require the user to distinguish dynamic attributes and
declare their type. I feel this is reasonable since the user must always know both when accessing a dynamic attribute. (This is actually the
basic nature of NoSQL – the schema is implicit in the application's business logic.) For this I invented a  notation for dynamic attribute tokens in DynamicActiveQuery. 

### Dynamic attribute tokens

The token is wrapped in curly braces containing the dynamic attribute name and optionally its type. Type may be omitted, in which case it defaults to `CHAR`, otherwise it is one of the datatypes allowed by the respective DBMS. Hierarchy in structured data is represented with dot separators: `grandparent.parent.child`.

Description | Token
--- | ---
General form | `{label[.label[...]]|type}`
`->color` with default datatype CHAR | `{color}`
`->price` with explicit datatype | `{price|DECIMAL(5,2)}`
`->address['city']` with default datatype CHAR | `{address.city}`
`->voltage['Vcc'][1]` with explicit datatype | `{voltage.Vcd.1|DOUBLE}`

Examples in queries

```php
$blackShirts = Product::find()
    ->where(['category' => Product::SHIRT, '{color}' => 'black'])
    ->all();
$cheapShirts = Product::find()
    ->where(['category' => Product::SHIRT])
    ->andWhere('{price.wholesale.12|DECIMAL(6,2)} < 20.00')
    ->select(['sale' => 'CONCAT("On sale at $", {price.discount})'])
    ->all();
```

## On datatypes

**tl;dr** While you're unlikely to lose data, you might not get the same datatype back that you put in. Use your understanding of the attribute in the context of your app to decide how to handle it after loading a model. When you want to use an attribute in a query, you have to specify its datatype.

The following discusses the details.

Types are a bit of a muddle and you need to take care. Currently, for all DBMSs except Maria, dynamic attribute are serialized and unserialized PHP's `json_encode()` and `json_decode()`. The same datatype conversion considerations and corner cases apply as in any use of JSON serialization, e.g. JSON has no integers, JSON arrays are different from PHP's, etc.

In Maria the situation is worse. Data is saved via SQL and retrieved via JSON. This may seem perverse but here's the logic.

- To save dynamic attribute in a Maria dynamic column, we have to use use the [COLUMN_CREATE('name', value)](https://mariadb.com/kb/en/mariadb/dynamic-columns/#column_create) SQL function. Maria infers an SQL datatype from the value and saves it with the dynamic column.
- When we load a record from the table into a model, we want the datatypes and values. We cannot retrieve the datatypes using Maria's [COLUMN_GET()](https://mariadb.com/kb/en/mariadb/dynamic-columns/#column_get) function so we instead use Maria's [COLUMN_JSON()](https://mariadb.com/kb/en/mariadb/dynamic-columns/#column_json) getter to fetch all the record's dynamic columns at once together with datatypes converted into JSON.

This is better than nothing and should be tolerable in a lot of cases but it is a bit weird and introduces a number of considerations regarding datatypes through the lifecycle of an AR model/record.

So the thing to remember:

- Maria: The PHP model is converted to SQL on save, which, on load, is converted to JSON and then to PHP.
- Everything else: The PHP model is converted to JSON on save and back to PHP on load.

With respect to specific types...

### Number types

SQL doesn't have float but has integer and decimal. JSON has only number, which is decimal and almost always an IEEE floating point double without NaN. PHP has integer and IEEE floats. This can get tricky but there is nothing really new here.

### Boolean

SQL doesn't have any such thing but JSON does. Maria (SQL) converts to int 0 or 1 on save. DMBS using JSON retain boolean type.

### Null

Maria does not save a dynamic column with a null value, thus

```sql
SELECT COLUMN_CREATE('a', 1, 'b', null) = COLUMN_CREATE('a', 1);
>> 1
```

So `$product->foo = null; $product->save();` actually deletes dynamic attribute 'foo' in the corresponding DB record.

This is perfectly reasonable. The meaning and purpose of SQL NULL makes no sense for dynamic fields. The 'x' not existing in the record a better representation for 'x' not existing than a serialized data element with the funny NULL value/type.

So, even though JSON can adequately represent a PHP null, DynamicActiveRecord does promise to return a PHP null if you try to save one. If you do $x = null then PHP considers $x to be "unset".

### Array

DynamicActiveRecord saves PHP arrays such that they are associative on load. In other words, you may as well use string keys because they will be strings on load and they need to be strings when dynamic attribute names are used in queries.

### Object

DynamicActiveRecord converts to PHP array before save so you'd be better of not using PHP objects.
