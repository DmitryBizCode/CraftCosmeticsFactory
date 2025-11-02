CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quantity INT NOT NULL CHECK (quantity > 0),
    client_id INT UNSIGNED NOT NULL,
    recipes_id INT UNSIGNED NOT NULL,

    FOREIGN KEY (client_id) REFERENCES clients(id) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (recipes_id) REFERENCES recipes(id),
    PRIMARY KEY (id)
);