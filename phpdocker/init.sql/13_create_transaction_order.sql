CREATE TABLE IF NOT EXISTS transaction_order (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    transaction_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,

    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE CASCADE ON DELETE CASCADE,
    PRIMARY KEY (id),
    UNIQUE KEY unique_transaction_order (transaction_id, order_id)
);

