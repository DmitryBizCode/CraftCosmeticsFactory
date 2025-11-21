CREATE TABLE IF NOT EXISTS iot_eco_values (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    iot_eco_id INT UNSIGNED NOT NULL,
    value FLOAT NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

    FOREIGN KEY (iot_eco_id) REFERENCES iot_eco(id) ON UPDATE CASCADE ON DELETE CASCADE,
    PRIMARY KEY (id),
    INDEX idx_iot_eco_created (iot_eco_id, created_at)
);

