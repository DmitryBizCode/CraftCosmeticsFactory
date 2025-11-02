CREATE TABLE IF NOT EXISTS rentals (
    rental_id INT NOT NULL AUTO_INCREMENT,
    start_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    end_date DATETIME,
    status VARCHAR(50) NOT NULL,
    price FLOAT NOT NULL,
    payment_status VARCHAR(255) NOT NULL DEFAULT 'unpaid',
    description TEXT NOT NULL,
    #add description
    container_id INT NOT NULL,
    client_id INT NOT NULL,

    FOREIGN KEY (container_id) REFERENCES containers(container_id),
    FOREIGN KEY (client_id) REFERENCES clients(client_id),
    PRIMARY KEY (rental_id)
);