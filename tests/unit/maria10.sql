DROP TABLE IF EXISTS `product` CASCADE;
DROP TABLE IF EXISTS `supplier` CASCADE;

CREATE TABLE `product` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(128),
    `dynamic_columns` BLOB,
    PRIMARY KEY (`id`)
) ENGINE =InnoDB DEFAULT CHARSET =utf8;

CREATE TABLE `supplier` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(128),
    `dynamic_columns` BLOB,
    PRIMARY KEY (`id`)
) ENGINE =InnoDB DEFAULT CHARSET =utf8;

INSERT INTO `product` (name, dynamic_columns) VALUES (
    'product1',
    COLUMN_CREATE(
        'supplier_id', 1,
        'str', 'value1',
        'int', 123,
        'float', 123.456,
        'bool', TRUE,
        'null', null,
        'children', COLUMN_CREATE(
            'str', 'value1',
            'int', 123,
            'float', 123.456,
            'bool', TRUE,
            'null', null
        )
    ));

INSERT INTO `supplier` (id, name, dynamic_columns) VALUES (
    1,
    'One',
    COLUMN_CREATE(
        'address', COLUMN_CREATE(
            'line1', 'Hoffmannstr. 51',
            'line2', 'D81379 München',
            'country', 'de'
        )
    )), (
    2,
    'Two',
    COLUMN_CREATE(
        'address', COLUMN_CREATE(
            'line1', '100 Foo St.',
            'city', 'Barton',
            'state', 'VT',
            'country', 'us'
        )
    ));

