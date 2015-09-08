# Should dynamic-ar support binary data?

Tom Worster  
2015-09-08

The initial release of dynamic-ar (DAR) was focussed on [Maria Dynamic Columns](https://mariadb.com/kb/en/mariadb/dynamic-columns/), which (mostly) supports the same data types as normal MySQL table columns, including BINARY, which is saved as a variable length string with binary charset. A query can use the `COLUMN_CREATE`, `COLUMN_ADD`, `COLUMN_GET` and other SQL functions to read and write binary data in dynamic columns.

I felt that if Maria supports binary dynamic columns then DAR should support binary dynamic attributes.

However, `COLUMN_GET` requires the dynamic column name, which DAR does not know (because of the requirement that it populate all dynamic attributes when loading a record), so DAR uses `COLUMN_JSON` instead*. But JSON cannot represent arbitrary binary data. JSON's scalars are limited to number, bool, null and string. And JSON strings are Unicode, which in our implementation needs to be encoded as UTF-8<sup>†</sup>.

DAR currently implements transparent encoding and decoding of binary data, using [data URIs](https://en.wikipedia.org/wiki/Data_URI_scheme), i.e. strings that aren't valid UTF-8. Thus DAR does not actually use Maria's dynamic column BINARY data type.

Now we are working on adding support for PostgreSQL jsonb and researching MySQL 5.7's JSON features. Both of these are based on JSON, a very different approach from Maria's dynamic columns, which is based on SQL. The premise of the argument for binary support (if the DB supports it, so should DAR) starts to wobble. Postgres' and MySQL's JSON cannot store arbitrary binary data and Maria's limitations mean DAR cannot use its binary dynamic column storage.


#### Costs of supporting binary dynamic attributes with transparent encoding

- Performance. On save, DAR tests every dynamic attribute (recursing nested structures) to see if it can be directly represented as a JSON scalar and encodes those that can't (and strings beginning with the encoding header). Complementary decoding after load is only slightly less complex.
- Code complexity
- Possible confusion. A user unaware of the encoding might use an operator on an encoded field that won't behave as expected.
- Data size. Encoded data is about one third larger than in its binary form, more for small strings. In my opinion this is not a big problem unless the user doesn't know it's happening. Failure to plan for it could lead to surprises such as overflowing the table column storing dynamic attributes.


#### Benefits

- The flexibility and convenience of using arbitrary PHP strings without needing to think about Unicode.
- Prevents runtime errors arising from attribute values that can't be saved as JSON and therefore relieves the client code from associated error handling.


#### On balance

The [principle of least astonishment](https://en.wikipedia.org/wiki/Principle_of_least_astonishment) is a bit ambiguous here. It could be astonishing to get a database exception on saving a model with a Latin-1 string (especially if the error message doesn't explain that only UTF-8 is allowed). On the other hand, I already mentioned possible surprises if the user doesn't know that non-UTF-8 strings are encoded as data URIs. Personally, I think POLA pushes towards binary support.

But DAR is moving towards being a more JSON-oriented feature. So there's an argument that we could educate the user and impose the specific constraints of JSON on the dynamic attribute strings of DynamicActiveRecord models. 

---

  
**Notes**

\* You might try to fetch all dynamic columns by using `COLUMN_LIST` to get columns names and then loading the values with `COLUMN_GET`s in a subsequent query. There are two complications. First, DAR supports nested dynamic attributes stored in nested Maria dynamic columns. So the second query would determine which dynamic columns returned by `COLUMN_LIST` in the first query themselves hold dynamic column blobs (using `COLUMN_CHECK`). The third query gets the scalars with `COLUMN_GET` and nested dynamic column names using `COLUMN_LIST` on the rest. The process may need to recurse the second and third queries. The second problem is that `COLUMN_GET` requires a dynamic column's name and [data type](https://mariadb.com/kb/en/mariadb/dynamic-columns/#column_get). DAR doesn't know the types so the best it could do is cast them all to CHAR, which would cause big problems. Hence DAR uses `COLUMN_JSON` to read dynamic columns.

<sup>†</sup> We are, for unrelated reasons, limited to UTF-8 encoding so our JSON strings need to be valid UTF-8.
