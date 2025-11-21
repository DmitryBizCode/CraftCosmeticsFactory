CREATE TABLE IF NOT EXISTS transactions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    value FLOAT NOT NULL CHECK (value > 0),
    currency VARCHAR(3) NOT NULL,
    date DATE NOT NULL,
    status VARCHAR(25) NOT NULL DEFAULT 'Pending',
    description TEXT,
    staff_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,

    FOREIGN KEY (staff_id) REFERENCES staff(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON UPDATE CASCADE ON DELETE RESTRICT,

    PRIMARY KEY (id)
);