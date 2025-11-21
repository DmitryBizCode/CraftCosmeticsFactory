CREATE TABLE IF NOT EXISTS staff (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(100),
    address VARCHAR(255) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    settlement VARCHAR(255) NOT NULL,
    access_layer INT UNSIGNED NOT NULL,

    PRIMARY KEY (id)
);

-- Генерація тестових даних для staff
INSERT INTO staff (first_name, last_name, address, email, phone_number, settlement, access_layer) VALUES
('John', 'Smith', '123 Main Street', 'accountant@malitech.com', '+380501234567', 'Kyiv', 2),
('Maria', 'Johnson', '456 Tech Avenue', 'engineer1@malitech.com', '+380502345678', 'Kyiv', 3),
('David', 'Williams', '789 Innovation Road', 'engineer2@malitech.com', '+380503456789', 'Lviv', 3);