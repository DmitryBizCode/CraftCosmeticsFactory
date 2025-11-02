CREATE TABLE IF NOT EXISTS iot (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    start_value FLOAT NOT NULL, 
    end_value FLOAT NOT NULL,
    description TEXT,

    PRIMARY KEY (id)
);
