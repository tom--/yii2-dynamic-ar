CREATE TABLE product (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(32),
    upc VARCHAR(32),
    title VARCHAR(255),
    price DECIMAL(9, 2),
    stock INT(11),
    details LONGBLOB NOT NULL
);
