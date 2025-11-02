CREATE TABLE IF NOT EXISTS recipes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    time_to_complete DATETIME NOT NULL,
    description TEXT,

    PRIMARY KEY (id)
);