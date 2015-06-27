# Dynamic Active Record

The [yii2-dynamic-ar](https://github.com/tom--/dynamic-ar) extension adds NoSQL-like documents to
[Yii 2 Framework](http://www.yiiframework.com/)'s
[Active Record ORM](http://www.yiiframework.com/doc-2.0/guide-db-active-record.html).



### Maria Dynamic Columns and PostgreSQL jsonb

[Dynamic Columns](https://mariadb.com/kb/en/mariadb/dynamic-columns/)
in [Maria 10.0](https://mariadb.com/kb/en/mariadb/what-is-mariadb-100/)+
and [jsonb column types](http://www.postgresql.org/docs/9.4/static/datatype-json.html)
and [functions](http://www.postgresql.org/docs/9.4/static/functions-json.html) in
in [PostgreSQL 9.4](http://www.postgresql.org/)+
provide, in effect, a [NoSQL document](https://en.wikipedia.org/wiki/Document-oriented_database)
attached to every row of an SQL table. It's a powerful
feature that allows you to do things that have been hard in relational DBs.
Problems that might drive you to Couch or Mongo, or to commit a crime like
[EAV](https://en.wikipedia.org/wiki/Entity%E2%80%93attribute%E2%80%93value_model)
to your schema, can suddenly be easy when

- records can have any number of attributes,
- attribute names can be made up on the fly,
- the dynamic attribute names don't appear in the schema,
- dynamic attributes can be structured like an associative array.

Dynamic AR works for Maria now and will come to PostgreSQL in the future.


## Example

An online shopping site has a table that stores info about each product.

```sql
CREATE TABLE product (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(32),
    upc VARCHAR(32),
    title VARCHAR(255),
    price DECIMAL(9, 2),
    stock INT(11),
    details LONGBLOB NOT NULL
);
```

In this (simplistic) example, `details` will hold the Maria
[Dynamic Column blob](https://mariadb.com/kb/en/mariadb/dynamic-columns/) and is
declared in the model class by the `dynamicColumn()` method. Everything else in a Dynamic AR
class declaration is familiar AR stuff.

```php
class Product extends \spinitron\dynamicAr\DynamicActiveRecord
{
    public static function tableName()
    {
        return 'product';
    }

    public static function dynamicColumn()
    {
        return 'details';
    }
}
```

Now we can do all the normal AR things with `Product` but in addition we can read, write and
update attributes not mentioned in the schema.

```php
$product = new Product([
    'sku' => 5463,
    'upc' => '234569',
    'price' => 4.99,
    'title' => 'Clue-by-four',
    'description' => 'Used for larting lusers or constructing things',
    'dimensions' => [
        'unit' => 'inch',
        'width' => 4,
        'height' => 2,
        'length' => 20,
    ],
    'material' => 'wood',
]);
$product->save();
```

Think of the `details` table column as holding a serialized associative array. But unlike
saving a JSON document in a text field, you can use dynamic attributes anywhere in your code,
including in queries,
just as you do with schema attributes. The differences are

- Nested attributes use dotted notation, e.g. `dimensions.length`
- Direct get and set of nested attributes on a model instance use the `getAttribute()`
and `setAttribute()` methods because PHP doesn't allow dotted notation in identifiers.
- When a dynamic attribute appears in a query, wrap it in bang-parens `(! … !)`,
e.g. `(! dimensions.length !)`. (Space between attribute name and its bang-parens is
optional so `(!material!)` is fine.)

For example

```php
$model = new Product([
    'title' => 'Car',
    'specs.fuel.tank.capacity' => 50,
    'specs.fuel.tank.capacity.unit' => 'liter',
]);
$model->setAttribute('specs.wheels.count', 4);
$model = Product::find()->where(['(!dimensions.length!)' => 10]);
$section = Product::find()
    ->select('CONCAT((! dimensions.width !), " x ", (! dimensions.height !))')
    ->where(['id' => 11])
    ->one();
```

The dot notation works anywhere Yii accepts an attribute name string, for example

```php
class Product extends \spinitron\dynamicAr\DynamicActiveRecord
{
    public function rules()
    {
        return [['dimensions.length', 'double', 'min' => 0.0]];
    }

    public function search($params)
    {
        $dataProvider = new \yii\data\ActiveDataProvider([
            'sort' => [
                'attributes' => [
                    'dimensions.length' => [
                        'asc' => ['(! dimensions.length !)' => SORT_DESC],
                        'desc' => ['(! dimensions.length !)' => SORT_ASC],
                    ],
                ],
            ],
            // ...
        ]);
    }
}
```

## Design principle

The design principle of Dynamic AR is

> You can read, write and unset model attributes that
aren't declared anywhere and their values may be structured, i.e. associative arrays.

A little more formally

- When you write to an attribute that isn't
    - a property declared in the model class
    - a column attribute
    - a virtual attribute with a setter method

    then you write to a dynamic attribute, either creating or updating it.

- Maria does not save a dynamic column that is set to SQL NULL: 
`COLUMN_CREATE('a', 1, 'b', null) = COLUMN_CREATE('a', 1)` is true. Thus if 
a record currently has a dynamic column 'b' and Maria recieves an update to 
the record setting 'b' to NULL then Maria removes it from the record.

- Hence there is no practical difference between a DynamicActiveRecord model 
having a dynamic attribute set to PHP null and not having the attribute at all.

- Therefore, if you read an attribute from model that isn't
    - a declared property
    - a column attribute
    - a virtual attribute
    - and the model has no dynamic attribute by that name

    then, unlike ActiveRecord, no exception is thrown and DynamicActiveRecord returns
    PHP null.

So even though DynamicActiveRecord supports `__isset()` and `issetAttribute()`, 
there's no need for an applications to use them on dynamic attributes.


## Further reading

Class reference

- [DynamciActiveRecord](spinitron-dynamicar-dynamicactiverecord.html)
- [DynamicActiveQuery](spinitron-dynamicar-dynamicactivequery.html)

More documentation

- [Datatypes](doc-datatypes.html) in PHP, SQL and JSON are not identical.
- [Design history](doc-design.html) – The projects original README.

Useful links

- [yii2-dynamic-ar project repo](https://github.com/tom--/dynamic-ar)
- [Yii 2 Framework](http://www.yiiframework.com/doc-2.0/guide-index.html)
- [Active Record guide](http://www.yiiframework.com/doc-2.0/guide-db-active-record.html)
- [Query Builder guide](http://www.yiiframework.com/doc-2.0/guide-db-query-builder.html)
- [Maria Dynamic Columns](https://mariadb.com/kb/en/mariadb/dynamic-columns/)
- [Sequel Pro Dynamic Columns bundle](https://github.com/tom--/sequel-pro-maria-dynamic-column)

## Questions, comments, issues

Use the [issue tracker](dynamic-ar/dynamic-ar/issues). Or you can easily find my email if you prefer.


- - -

Copyright (c) 2015 Spinitron LLC
