-- database/migrations/002_warehouses.sql
-- Migration to create the warehouses table required for Delhivery integration
CREATE TABLE IF NOT EXISTS warehouses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    pincode VARCHAR(10) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0
);
-- Insert a default warehouse (edit values as needed)
INSERT INTO warehouses (name, pincode, city, state, is_default)
VALUES (
        'Main Warehouse',
        '400001',
        'Mumbai',
        'Maharashtra',
        1
    ) ON DUPLICATE KEY
UPDATE name =
VALUES(name),
    pincode =
VALUES(pincode),
    city =
VALUES(city),
    state =
VALUES(state),
    is_default =
VALUES(is_default);