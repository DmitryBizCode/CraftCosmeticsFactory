CREATE TABLE IF NOT EXISTS routes (
    route_id INT NOT NULL AUTO_INCREMENT,
    origin_port_id INT NOT NULL,
    destination_port_id INT NOT NULL,
    estimated_time INT NOT NULL,
    distance FLOAT NOT NULL,

    FOREIGN KEY (origin_port_id) REFERENCES ports(port_id),
    FOREIGN KEY (destination_port_id) REFERENCES ports(port_id),
    PRIMARY KEY (route_id)
);