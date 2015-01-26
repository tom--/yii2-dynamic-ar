DROP TABLE IF EXISTS `product` CASCADE;

    CREATE TABLE `product` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(128),
        `dynamic_columns` BLOB,
        PRIMARY KEY (`id`)
    ) ENGINE =InnoDB DEFAULT CHARSET =utf8;

    INSERT INTO `product` (name, dynamic_columns) VALUES (
        'name1',
        COLUMN_CREATE(
            'str', 'value1',
            'int', 123,
            'float', 123.456,
            'bool', true,
            'null', null,
            'children', COLUMN_CREATE(
                'str', 'value1',
                'int', 123,
                'float', 123.456,
                'bool', true,
                'null', null
            )
        ));

    SELECT COLUMN_GET(`dynamic_columns`, 'float' AS DECIMAL(6,34)) FROM `product`;