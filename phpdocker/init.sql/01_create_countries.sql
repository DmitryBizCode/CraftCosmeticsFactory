CREATE TABLE IF NOT EXISTS countries (
    country_id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    interest_tax FLOAT NOT NULL,

    PRIMARY KEY (country_id)
);