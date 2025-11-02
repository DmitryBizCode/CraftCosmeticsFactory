CREATE TABLE IF NOT EXISTS container_client (
    container_client_id INT NOT NULL AUTO_INCREMENT,
    client_id INT NOT NULL,
    container_id INT NOT NULL,

    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE,
    FOREIGN KEY (container_id) REFERENCES containers(container_id) ON DELETE CASCADE,
    PRIMARY KEY (container_client_id)
);