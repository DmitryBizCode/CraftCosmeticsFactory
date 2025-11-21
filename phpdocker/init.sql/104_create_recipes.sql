CREATE TABLE IF NOT EXISTS recipes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    time_to_complete DECIMAL(10,4) NOT NULL COMMENT 'Time in hours',
    description TEXT,
    photo VARCHAR(255),
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    PRIMARY KEY (id)
);