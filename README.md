# Yii 2 Dynamic Active Record

These classes extend the Yii 2 Active Record abstraction to the NoSQL-like features of
[Dynamic Columns in Maria 10.0+](https://mariadb.com/kb/en/mariadb/dynamic-columns/)
and
[jsonb column types](http://www.postgresql.org/docs/9.4/static/datatype-json.html)
and
[functions in PostgreSQL 9.4+](http://www.postgresql.org/docs/9.4/static/functions-json.html).

This is useful for things like user profiles where you have some fixed columns and
a some rather less well defined set of profile properties that might be sparely populated
and/or fluid in definition as the application evolves. Another typical example is
product descriptions in a vendor's database. Washing machines need to be described
with properties relevant to them (capacity, spin speed, power) while lawn mowers
require other properties (motor type, weight, blade diameter).

SQL relational databases don't accommodate these requirements very comfortably while document
store databases do. Dynamic Active Record is for when you want to use these capabilities
through AR with certain tables within a coherent SQL relational database.

## Design and operational concept

### DynamicActiveRecord

When you use a DynamicActiveRecord object, any property you access is a dynamic column
(dyn-col) if it:

- isn't a normal DB column in the schema, and
- does not have its own getter or setter

Thus if `$product` is an instance of a `DynamicActiveRecord` class:

```php
$product->color = 'red';
```

Will write to the AR attribute 'color', if the table's schema has a column with
this name, or
will call `$product->setColor('red')` if you have declared this method, or finally will
write to a dyn-col named 'foo', either creating or updating this dyn-col for the record.

Dyn-cols can have array values, e.g.

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

The dyn-col's name must be a valid PHP label (e.g. it may not start with a digit) but child
labels can be any non-empty string.

All dyn-cols are populated from the DB when loading a model regardless of the query's `select`.

When writing a model to the DB, whether the operation is insert or update, the record will have
the same set of dyn-col that the model had. Thus if a DB record has a dyn-col 'speed' and its
corresponding model does not then an update operation will delete it.

### DynamicActiveQuery

We would like to be able to use dynamic columns with the same flexibility as schema column
attributes. DynamicActiveQuery should allow creation of SQL such as the following whether
the columns involved are dynamic or schema:

```sql
SELECT t1.price, CONCAT('Compare at $', t1.price) AS foo, MIN(t1.price, t1.discount) AS sale
FROM t1 JOIN t2 ON MIN(t1.price, t1.discount) > t2.cost
WHERE price * 1.33 < 50.00
ORDER BY MIN(0.66 * t1.price, t2.cost)
```

There is another challenge. From the Maria manual:

> SQL is a statically-typed language. The SQL interpreter needs to know the datatypes of
all expressions before the query is run (for example, when one is using prepared statements
and runs [a SELECT], the prepared statement API requires the server to inform the client
about the datatype of the column being read before the query is executed and the server can
see what datatype the column actually has).

So DynamicActiveQuery needs a way to:

1. Identify dyn-col names anywhere that a column name can appear in an ActiveQuery instance.
1. Choose an appropriate SQL type for the dyn-cols that if finds.

This cannot be automated without radical change to Yii's existing APIs that I do not like.
So I decided instead to require the DynamicActiveQuery user to distinguish dyn-cols and
declare their type. I feel this is reasonable given that the user needs to know both
of these to be able to use dyn-cols in her Yii application. For this I invented a specific
syntax, in general it has the form:

```
{label[.label[...]]|type}
```

In which type defaults to `char` when omitted and is otherwise one of the types allowed
by the respective DBMS. The parent.child notation allows access to elements in an array.
For example:

```php
$blackShirts = Product::find()
    ->where(['category' => Product::SHIRT, '{color}' => 'black'])
    ->all();
$cheapShirts = Product::find()
    ->where(['category' => Product::SHIRT])
    ->andWhere('{price.wholesale.12|DECIMAL(6,2)} < 20.00')
    ->all();
```


