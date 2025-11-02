CREATE TABLE IF NOT EXISTS ports (
    port_id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    location VARCHAR(255) NOT NULL,
    country_id INT NOT NULL,

    FOREIGN KEY (country_id) REFERENCES countries(country_id),
    PRIMARY KEY (port_id)
);