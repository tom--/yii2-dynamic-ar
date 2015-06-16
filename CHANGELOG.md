# yii2-dynamic-ar extension change log

## 0.1.0 May 21 2015

- Initial release

## 0.1.1 June 1 2015

- Bug: Empty array dynamic column value leads to invalid SQL: COLUMN_CREATE()

## 0.2.0 June 6 2015

- Enh: New attribute name getter method DynamicActiveRecord::allAttributes()

## 0.2.1 June 8 2015

- Bug: Empty array in DynamicActiveRecord::dynColSqlMaria() creates invalid COLUMN_CREATE() command

## 0.2.2 June 13 2015

- Enh: Allowed to use spaces in dyn attributes in queries, e.g. `(! attr !)`