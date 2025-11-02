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
