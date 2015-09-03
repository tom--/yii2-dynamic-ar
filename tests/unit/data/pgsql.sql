/**
 * This is the database schema for testing PgSQL support of Yii DAO and Active Record.
 * The database setup in config.php is required to perform then relevant tests:
 */

DROP TABLE IF EXISTS "composite_fk" CASCADE;
DROP TABLE IF EXISTS "order_item" CASCADE;
DROP TABLE IF EXISTS "order_item_with_null_fk" CASCADE;
DROP TABLE IF EXISTS "item" CASCADE;
DROP TABLE IF EXISTS "order" CASCADE;
DROP TABLE IF EXISTS "order_with_null_fk" CASCADE;
DROP TABLE IF EXISTS "category" CASCADE;
DROP TABLE IF EXISTS "customer" CASCADE;
DROP TABLE IF EXISTS "profile" CASCADE;
DROP TABLE IF EXISTS "null_values" CASCADE;
DROP TABLE IF EXISTS "type" CASCADE;
DROP TABLE IF EXISTS "constraints" CASCADE;

CREATE TABLE "constraints"
(
  "id" integer not null,
  "field1" varchar(255)
);


CREATE TABLE "profile" (
  "id" serial,
  "description" varchar(128) NOT NULL,
  PRIMARY KEY ("id")
);

CREATE TABLE "customer" (
  "id" serial,
  "email" varchar(128) NOT NULL,
  "name" varchar(128),
  "address" text,
  "status" integer DEFAULT 0,
  "profile_id" integer,
  PRIMARY KEY ("id")
);

CREATE TABLE "category" (
  "id" serial,
  "name" varchar(128) NOT NULL,
  PRIMARY KEY ("id")
);

CREATE TABLE "item" (
  "id" serial,
  "name" varchar(128) NOT NULL,
  "category_id" integer NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "FK_item_category_id" FOREIGN KEY ("category_id") REFERENCES "category" ("id") ON DELETE CASCADE
);

CREATE TABLE "order" (
  "id" serial,
  "customer_id" integer NOT NULL,
  "created_at" integer NOT NULL,
  "total" decimal(10,0) NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "FK_order_customer_id" FOREIGN KEY ("customer_id") REFERENCES "customer" ("id") ON DELETE CASCADE
);

CREATE TABLE "order_with_null_fk" (
  "id" serial,
  "customer_id" integer,
  "created_at" integer NOT NULL,
  "total" decimal(10,0) NOT NULL,
  PRIMARY KEY ("id")
);

CREATE TABLE "order_item" (
  "order_id" integer NOT NULL,
  "item_id"  integer NOT NULL,
  "quantity" integer NOT NULL,
  "subtotal" DECIMAL(10, 0) NOT NULL,
  PRIMARY KEY ("order_id", "item_id"),
  CONSTRAINT "FK_order_item_order_id" FOREIGN KEY ("order_id") REFERENCES "order" ("id") ON DELETE CASCADE,
  CONSTRAINT "FK_order_item_item_id" FOREIGN KEY ("item_id") REFERENCES "item" ("id") ON DELETE CASCADE
);


CREATE TABLE "order_item_with_null_fk" (
  "order_id" integer,
  "item_id" integer,
  "quantity" integer NOT NULL,
  "subtotal" decimal(10,0) NOT NULL
);

CREATE TABLE "composite_fk" (
  "id" integer NOT NULL,
  "order_id" integer NOT NULL,
  "item_id" integer NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "FK_composite_fk_order_item" FOREIGN KEY ("order_id","item_id") REFERENCES "order_item" ("order_id","item_id") ON DELETE CASCADE
);

CREATE TABLE null_values (
  "id" serial,
  "var1" INT NULL,
  "var2" INT NULL,
  "var3" INT DEFAULT NULL,
  "stringcol" VARCHAR (32) DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE "type" (
  "int_col" integer NOT NULL,
  "int_col2" integer DEFAULT '1',
  "smallint_col" smallint DEFAULT '1',
  "char_col" char(100) NOT NULL,
  "char_col2" varchar(100) DEFAULT 'something',
  "char_col3" text,
  "enum_col" char(1) CHECK ("enum_col" IN ('a', 'B')),
  "float_col" DOUBLE PRECISION NOT NULL,
  "float_col2" DOUBLE PRECISION DEFAULT '1.23',
  "blob_col" bytea,
  "numeric_col" decimal(5,2) DEFAULT '33.22',
  "time" timestamp NOT NULL DEFAULT '2002-01-01 00:00:00',
  "bool_col" boolean NOT NULL,
  "bool_col2" boolean DEFAULT 't',
  "ts_default" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "bit_col" BIT(8) NOT NULL DEFAULT b'10000010'
);

INSERT INTO "profile" (description) VALUES ('profile customer 1');
INSERT INTO "profile" (description) VALUES ('profile customer 3');

INSERT INTO "customer" (email, name, address, status, profile_id) VALUES ('user1@example.com', 'user1', 'address1', 1, 1);
INSERT INTO "customer" (email, name, address, status) VALUES ('user2@example.com', 'user2', 'address2', 1);
INSERT INTO "customer" (email, name, address, status, profile_id) VALUES ('user3@example.com', 'user3', 'address3', 2, 2);

INSERT INTO "category" (name) VALUES ('Books');
INSERT INTO "category" (name) VALUES ('Movies');

INSERT INTO "item" (name, category_id) VALUES ('Agile Web Application Development with Yii1.1 and PHP5', 1);
INSERT INTO "item" (name, category_id) VALUES ('Yii 1.1 Application Development Cookbook', 1);
INSERT INTO "item" (name, category_id) VALUES ('Ice Age', 2);
INSERT INTO "item" (name, category_id) VALUES ('Toy Story', 2);
INSERT INTO "item" (name, category_id) VALUES ('Cars', 2);

INSERT INTO "order" (customer_id, created_at, total) VALUES (1, 1325282384, 110.0);
INSERT INTO "order" (customer_id, created_at, total) VALUES (2, 1325334482, 33.0);
INSERT INTO "order" (customer_id, created_at, total) VALUES (2, 1325502201, 40.0);

INSERT INTO "order_with_null_fk" (customer_id, created_at, total) VALUES (1, 1325282384, 110.0);
INSERT INTO "order_with_null_fk" (customer_id, created_at, total) VALUES (2, 1325334482, 33.0);
INSERT INTO "order_with_null_fk" (customer_id, created_at, total) VALUES (2, 1325502201, 40.0);

INSERT INTO "order_item" (order_id, item_id, quantity, subtotal) VALUES (1, 1, 1, 30.0);
INSERT INTO "order_item" (order_id, item_id, quantity, subtotal) VALUES (1, 2, 2, 40.0);
INSERT INTO "order_item" (order_id, item_id, quantity, subtotal) VALUES (2, 4, 1, 10.0);
INSERT INTO "order_item" (order_id, item_id, quantity, subtotal) VALUES (2, 5, 1, 15.0);
INSERT INTO "order_item" (order_id, item_id, quantity, subtotal) VALUES (2, 3, 1, 8.0);
INSERT INTO "order_item" (order_id, item_id, quantity, subtotal) VALUES (3, 2, 1, 40.0);

INSERT INTO "order_item_with_null_fk" (order_id, item_id, quantity, subtotal) VALUES (1, 1, 1, 30.0);
INSERT INTO "order_item_with_null_fk" (order_id, item_id, quantity, subtotal) VALUES (1, 2, 2, 40.0);
INSERT INTO "order_item_with_null_fk" (order_id, item_id, quantity, subtotal) VALUES (2, 4, 1, 10.0);
INSERT INTO "order_item_with_null_fk" (order_id, item_id, quantity, subtotal) VALUES (2, 5, 1, 15.0);
INSERT INTO "order_item_with_null_fk" (order_id, item_id, quantity, subtotal) VALUES (2, 3, 1, 8.0);
INSERT INTO "order_item_with_null_fk" (order_id, item_id, quantity, subtotal) VALUES (3, 2, 1, 40.0);


/**
 * (PgSQL-)Database Schema for validator tests
 */

DROP TABLE IF EXISTS "validator_main" CASCADE;
DROP TABLE IF EXISTS "validator_ref" CASCADE;

CREATE TABLE "validator_main" (
  "id"     serial,
  "field1" VARCHAR(255),
  PRIMARY KEY ("id")
);

CREATE TABLE "validator_ref" (
  "id"      serial,
  "a_field" VARCHAR(255),
  "ref"     integer,
  PRIMARY KEY ("id")
);

INSERT INTO "validator_main" (id, field1) VALUES (1, 'just a string1');
INSERT INTO "validator_main" (id, field1) VALUES (2, 'just a string2');
INSERT INTO "validator_main" (id, field1) VALUES (3, 'just a string3');
INSERT INTO "validator_main" (id, field1) VALUES (4, 'just a string4');
INSERT INTO "validator_ref" (a_field, ref) VALUES ('ref_to_2', 2);
INSERT INTO "validator_ref" (a_field, ref) VALUES ('ref_to_2', 2);
INSERT INTO "validator_ref" (a_field, ref) VALUES ('ref_to_3', 3);
INSERT INTO "validator_ref" (a_field, ref) VALUES ('ref_to_4', 4);
INSERT INTO "validator_ref" (a_field, ref) VALUES ('ref_to_4', 4);
INSERT INTO "validator_ref" (a_field, ref) VALUES ('ref_to_5', 5);

-- new rows for dynamic columns testing
DROP TABLE IF EXISTS "product" CASCADE;
DROP TABLE IF EXISTS "supplier" CASCADE;
DROP TABLE IF EXISTS "missing_dyn_column" CASCADE;

CREATE TABLE "product" (
    "id" serial,
    "name" VARCHAR(128),
    "dynamic_columns" jsonb,
    PRIMARY KEY ("id")
);

CREATE TABLE "supplier" (
    "id" serial,
    "name" VARCHAR(128),
    "dynamic_columns" jsonb,
    PRIMARY KEY ("id")
);

CREATE TABLE "missing_dyn_column" (
    "id" serial,
    "name" VARCHAR(128),
    PRIMARY KEY ("id")
);

INSERT INTO "product" (id, name, dynamic_columns) VALUES (
    1,
    'product1',
    '{
        "supplier_id": 1,
        "str": "value1",
        "int": 123,
        "float": 123.456,
        "bool": 1,
        "null": null,
        "children": {
            "str": "value1",
            "int": 123,
            "float": 123.456,
            "bool": 1,
            "null": null
        }
    }'::jsonb);

INSERT INTO "product" (id, name, dynamic_columns) VALUES (
    2,
    'product2',
    '{"int":456}'::jsonb);

INSERT INTO "product" (id, name, dynamic_columns) VALUES (
    3,
    'product3',
    '{
        "int": 792,
        "children": {
            "str": "value3"
        }
    }'::jsonb);

INSERT INTO "supplier" (id, name, dynamic_columns) VALUES (
    3,
    'One',
    '{
        "address": {
            "line1": "Hoffmannstr. 51",
            "line2": "D81379 München",
            "country": "de"
        }
    }'::jsonb), (
    4,
    'Two',
    '{
        "address": {
            "line1": "100 Foo St.",
            "city": "Barton",
            "state": "VT",
            "country": "us"
        }
    }'::jsonb);

INSERT INTO "supplier" (id, name, dynamic_columns) VALUES (
    1,
    'three',
    '{
        "address": {
            "country": "england"
        }
    }'::jsonb);

INSERT INTO "supplier" (id, name, dynamic_columns) VALUES (
    10,
    'binary',
    '{
        "name": "c1c2c3c4c5c6"
    }'::jsonb);

INSERT INTO "missing_dyn_column" (name) VALUES (
    'one'
   );
