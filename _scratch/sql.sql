SELECT (COLUMN_GET(COLUMN_GET(COLUMN_CREATE('child', COLUMN_CREATE('int', 123)), 'child' AS char), 'int' AS char) * 2);

SELECT (COLUMN_GET(COLUMN_CREATE('int', 123), 'int' AS char) * 2);


SELECT *,
    (
        COLUMN_GET(
            COLUMN_GET(`dynamic_columns`, 'children' AS char),
            'int' AS char
        ) * 2
    ) AS customColumn,
    COLUMN_JSON(`dynamic_columns`) AS `dynamic_columns`
FROM `product` WHERE `name`='product1'



SELECT 2 * COLUMN_GET(
    COLUMN_GET(
        COLUMN_CREATE('a', COLUMN_CREATE('b', 123)),
        'a' AS char
    ),
    'b' AS char
);

column_get(column_get(…, 'foo' as char), …)