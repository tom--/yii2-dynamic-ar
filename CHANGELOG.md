# yii2-dynamic-ar extension change log

## 0.1.0 May 21 2015

- Initial release

### 0.1.1 June 1 2015

- Bug: Empty array dynamic column value leads to invalid SQL: COLUMN_CREATE()

## 0.2.0 June 6 2015

- Enh: New attribute name getter method DynamicActiveRecord::allAttributes()

### 0.2.1 June 8 2015

- Bug: Empty array in DynamicActiveRecord::dynColSqlMaria() creates invalid COLUMN_CREATE() command

### 0.2.2 June 13 2015

- Enh: Allowed to use spaces in dyn attributes in queries, e.g. `(! attr !)`

### 0.2.3 June 25 2015

- Bug: Inconsistencies in magic methods
- Refactor: Reorganize DynamicActiveRecord, made each magic method a wrapper

## 0.3.0 Sep 2 2015

- Bug: Critical error in DynamicActiveRecord::populateRecord() and DynamicActiveQuery::prepare()
- Bug: Critical corner cases with certain types in DynamicActiveRecord::dynColSqlMaria
- Refactor: Breaking changes! In DynamicActiveRecord dotKeyValues() replaces dotKeys(),  dotAttributeNames() replaces allAttributes()
- Enh: Add ValueExpresson class for disambiguation of certain SQL types
- Enh: Add DynamicActiveRecord::columnExpression() method as alternative to (! !) notation
- Enh: Get project in packagist.org, fixing composer.json
- Enh: Add tests for nested DAR models
- Enh: Add documentation static site generator and new docs
- Enh: Add DynamicActiveRecord::docAttributes() to get all attributes in a map of dot-notation keys to values
- Bug: Missing docblock tags
- Refactor: Don't expose your privates
