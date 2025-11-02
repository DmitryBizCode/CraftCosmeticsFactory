CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    quantity FLOAT NOT NULL,
    unit ENUM('g', 'kg', 'ml', 'l', 'pcs') NOT NULL DEFAULT 'g',
    temperature FLOAT,
    lightening FLOAT,
    description TEXT,
    allergen BOOLEAN NOT NULL DEFAULT FALSE,

    PRIMARY KEY (id)
);