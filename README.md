# yii2-dynamic-ar extension

**Dynamic attributes in Active Record and Active Query for SQL tables**


NoSQL-like features have been appearing in some SQL relational databases, including [Dynamic Columns in Maria 10.0+](https://mariadb.com/kb/en/mariadb/dynamic-columns/)
and
[jsonb column types](http://www.postgresql.org/docs/9.4/static/datatype-json.html)
and
[functions in PostgreSQL 9.4+](http://www.postgresql.org/docs/9.4/static/functions-json.html).

These features are useful for representing entities that have properties that don't comfortably map onto table columns, for example because:

- the number of properties needed to describe all entities is large
- individual entities are described by a small subset of the properties
- the set of properties is fluid, so that after initial application deployment, properties can be introduced or retired fairly requently

The classic example is
product descriptions in a vendor's database. Washing machines need to be described
with properties relevant to them (capacity, spin speed, power) while lawn mowers
require other properties (motor type, weight, blade diameter). Imagine the set of properties for a large general shopping site like Amazon.

Such entities are easily represented in a NoSQL docuemnt store such as CouchDB or MongoDB. But until recently representing them in relational database tables typically means choosing a method that involves some kind of compromise:

- Mapping one property to one column can lead to far too many columns that a very sparsly populated and columns must be frequently added and removed (the table description could be as dynamic as the data in it!).
- Sub-classing the entities into a hierarchy of types (to the extend that the entities allow such categorization) can allow mapping the types to a schema of related tables. This can mitigate sparce column population and somewhat contain the impact of schema changes at the cost of tables proliferation. 
- EAV tables make anything except the most primitive search and querying very complex.
- Serializing the properties into a column containing all the dynamic properties means you cannot query individual properties.

However, Maria 10 and Postgress 9.4 have the ability to serialize an arbitrary set of (structured) properties while allowing them to be used in queries.


## Design and operation

DynamicActiveRecord extends ActiveRecord to represent structured dynamic attributes that are stored in serialized form in the database. If the particular DBMS has features to support this then they are used, otherwise JSON is used. In the case that the DBMS allows querying of the serialized properties DynamicActiveQuery extends ActiveQuery represent such queries.

### DynamicActiveRecord

In a DynamicActiveRecord object, any property you access is a dynamic attribute when:

- it isn't a normal table column in the schema, and
- it does not have its own magic getter or setter

Thus if `$product` is an instance of a `DynamicActiveRecord` class:

```php
$product->color = 'red';
```

Will write to the AR attribute 'color', if the table's schema has a column with
this name, or
will call `$product->setColor('red')` if you have declared this method, otherwise it will
write to a dynamic attribute named 'foo', either creating or updating the dynamic attribute.

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

A dynamic attribute's name must be a valid PHP label (e.g. it may not start with a digit) but child
labels can be any non-empty string.

All dynamic attributes are populated from the DB when loading a model, regardless of the query's `select` property.

When writing a model to the DB, whether the operation is insert or update, the DB record will have
the same set of dyn-col that the model had. For example, let's say there is a record in the `product` table that has the serialized property `speed` and a PK `id` of 7. If we now instantiate a new `Product` model, set its `id` to 7 and save it without setting the dynamic attribute `speed` then that property is deleted in the DB record.

### DynamicActiveQuery

DynamicActiveQuery only exists for those DMBSs that provide a way to query elements in data structures serialized to a field.

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

> SQL is a statically-typed language. The SQL interpreter needs to know the datatypes of
all expressions before the query is run (for example, when one is using prepared statements
and runs [a SELECT], the prepared statement API requires the server to inform the client
about the datatype of the column being read before the query is executed and the server can
see what datatype the column actually has).

So DynamicActiveQuery needs a way to:

1. Identify dyn-col names anywhere that a column name can appear in an ActiveQuery instance.
1. Choose an appropriate SQL type for the dyn-cols that if finds.

The first cannot be automated without constraining the requirements or throigh radical change in ActiveQuery. Automating the second seems intractibe. So I decided instead to require the user to distinguish dynamic attributes and
declare their type. I feel this is reasonable given that the user needs to know both
of these to be able to use dynamic attributes in her Yii application. (This is in fact the
basic nature of NoSQL – the schema is implicit in the application business logic.) For this I invented a token syntax for dynamic attributes in DynamicActiveQuery. 

### Dynamic attribute tokens

The token is wrapped in curly braces containing the dynamic attribute name and optionally its type. Type may be omitted, in which case it defaults to `char`. Otherwise it is otherwise one of the datatypes allowed by the respective DBMS. Hierarchy in structured data is represenyed with dot separators: `grandparent.parent.child`.


---|---
General form | `{label[.label[...]]|type}`
`color` with default datatype char | `{color}`
`price` with explcit datatype | `{price|DECIMAL(5,2)}`
`address['city']` with default datatype char | `{address.city}`
`voltage['Vcc'][1]` with explicit datatype | `{coltage.Vcd.1|DOUBLE}`

Example uses in queries

```php
$blackShirts = Product::find()
    ->where(['category' => Product::SHIRT, '{color}' => 'black'])
    ->all();
$cheapShirts = Product::find()
    ->where(['category' => Product::SHIRT])
    ->andWhere('{price.wholesale.12|DECIMAL(6,2)} < 20.00')
    ->select('sale' => 'CONCAT("On sale at $", {price.discount})')
    ->all();
```

## On datatypes

Types are a bit of a muddle and you need to take care. Currently, for all DBs except Maria, dynamic attribute data is serialized on save to the DB via PHP's `json_encode()` and unserialized on load with `json_decode()`. In Maria it is saved using Maria's specific SQL function [COLUMN_CREATE()](https://mariadb.com/kb/en/mariadb/dynamic-columns/).

### Number types



### Boolean

SQL doesn't have any such thing but JSON does. Maria (SQL) converts to int 0 or 1 on save. DMBS using JSON retain boolean type.

### Null

Maria does not save a dynamic column with a null value, thus

```sql
SELECT COLUMN_CREATE('a', 1, 'b', null) = COLUMN_CREATE('a', 1);
>> 1
```

So `$product->foo = null; $product->save();` actually deletes dynamic attribute 'foo' in the DB.
