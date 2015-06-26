# On datatypes

**tl;dr** While you're unlikely to lose data, you might not get the same datatype back that
you put in. Use your understanding of the attribute in the context of your app to decide
 how to handle it after loading a model. When you want to use an attribute in a query, you
 have to specify its datatype.

Types are a bit of a muddle between DynamicActiveRecord, Maria Dyanic Columns and JSON
and you may need to take some care. For all DBMSs except Maria, dynamic attribute
are (will be) serialized and unserialized PHP's `json_encode()` and `json_decode()`. The same
datatype conversion considerations and corner cases apply as in any use of JSON serialization,
e.g. JSON has no integers, JSON arrays are different from PHP's, etc.

In Maria the situation is worse. Data is saved via SQL and retrieved via JSON. This may seem
perverse but here's the logic.

- To save dynamic attribute in a Maria dynamic column, we have to use use the
[COLUMN_CREATE('name', value)](https://mariadb.com/kb/en/mariadb/dynamic-columns/#column_create)
 SQL function. Maria infers an SQL datatype from the value and saves it with the dynamic column.
- When we load a record from the table into a model, we want the datatypes and values.
We cannot retrieve the datatypes using Maria's
[COLUMN_GET()](https://mariadb.com/kb/en/mariadb/dynamic-columns/#column_get)
function so we instead use Maria's
[COLUMN_JSON()](https://mariadb.com/kb/en/mariadb/dynamic-columns/#column_json) getter
to fetch all the record's dynamic columns at once together with data converted
into JSON's types.

This is better than nothing and should be tolerable in a lot of cases but it is a bit weird
and introduces a number of considerations regarding datatypes through the life-cycle of an
AR model/record.

So the data life-cycle with Maria is, roughly

1. String values that JSON cannot represent are encoded as base64 data URIs.
1. The Dynamic AR model with PHP types is converted to an SQL command string on save
1. Maria infers SQL types from the values in the SQL command an saves them with the data
1. On load, Dynamic AR requests the saved dynamic columns in JSON format from Maria
1. (A but in Maria's JSON encoder is worked around)
1. Dynamic AR converts the JSON to PHP
1. Data URI values are decoded.


### Null

Maria does not save a dynamic column with a null value, thus

```sql
SELECT COLUMN_CREATE('a', 1, 'b', null) = COLUMN_CREATE('a', 1);
>> 1
```

So `$product->foo = null; $product->save();` actually deletes dynamic attribute 'foo' in the
corresponding DB record (assuming that record had a 'foo' before the save).

This is perfectly reasonable. The meaning and purpose of SQL NULL makes no sense for dynamic
fields. The correct way to represent something as not existing in dynamic columns is
for it to not exist. It isn't so straightforward in a normal SQL table column, which is why
it can have the NULL type/value.

So, even though JSON can adequately represent a PHP null, DynamicActiveRecord does
not (actually could not) distinguish dynamic attributes with null values
from nonexistent ones over the save/load cycle.

So consider setting a dynamic attribute to PHP null as being the same as unsetting it.
And when you read an attribute that doesn't exist, DynamicActiveRecord returns PHP null,
unlike ActiveRecord which throws an exception.

### Boolean

SQL doesn't have bools but JSON does. Maria (SQL) converts to INT 0 or 1 on save so
you should get integer 0 or 1 after loading from the DB.


### Number types

SQL doesn't have float but has integer and decimal. JSON has only number, which is a decimal
and usually converted from and to an IEEE floating point double without NaN on
each end of the serialization. PHP has integer and IEEE floats.
This can get tricky but these are old problems that have little to do with Dynamic AR.


### Array

DynamicActiveRecord saves PHP arrays such that they are associative on load. In other words,
you may as well use string keys because they will be strings on load and they need to be strings
when dynamic attribute names are used in queries.

Empty arrays are not saved in Maria.

### Object

DynamicActiveRecord casts objects to PHP array before save so may as well use arrays instead
of objects.


- - -

Copyright (c) 2015 Spinitron LLC
