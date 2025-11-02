CREATE TABLE IF NOT EXISTS shipments (
    shipment_id INT NOT NULL AUTO_INCREMENT,
    departure_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    arrival_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(255),
    rental_id INT NOT NULL,
    route_id INT NOT NULL,

    FOREIGN KEY (rental_id) REFERENCES rentals(rental_id),
    FOREIGN KEY (route_id) REFERENCES routes(route_id),
    PRIMARY KEY (shipment_id)
);