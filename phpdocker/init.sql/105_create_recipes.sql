CREATE TABLE IF NOT EXISTS recipes (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    time_to_complete DATETIME NOT NULL,
    description TEXT,
    cost FLOAT NOT NULL,
    container_id INT NOT NULL,

    FOREIGN KEY (container_id) REFERENCES containers(container_id),
    PRIMARY KEY (id)
);