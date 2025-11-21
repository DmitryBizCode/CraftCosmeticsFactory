CREATE TABLE IF NOT EXISTS recipes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    time_to_complete DECIMAL(10,4) NOT NULL COMMENT 'Time in hours',
    description TEXT,
    photo VARCHAR(255),
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    rating DECIMAL(3,1) DEFAULT 5.0,
    review_count INT UNSIGNED DEFAULT 0,

    PRIMARY KEY (id)
);

-- Генерація тестових даних для рецептів
INSERT INTO recipes (name, time_to_complete, description, price, rating, review_count) VALUES
('Hydrating Face Serum', 2.5, 'Deeply hydrating serum with hyaluronic acid and aloe vera for all skin types', 45.99, 4.5, 1250),
('Anti-Aging Night Cream', 4.0, 'Rich night cream with retinol and vitamin C for reducing fine lines', 65.50, 4.8, 2100),
('Brightening Day Cream', 3.0, 'Lightweight day cream with vitamin C for radiant, even-toned skin', 52.00, 4.6, 1800),
('Soothing Body Lotion', 2.0, 'Gentle body lotion with shea butter and aloe vera for dry skin', 28.99, 4.4, 950),
('Nourishing Face Oil', 1.5, 'Blend of jojoba and coconut oils for intense hydration', 38.75, 4.7, 1400),
('Gentle Cleansing Balm', 2.5, 'Melting balm with beeswax and oils for effective makeup removal', 42.50, 4.5, 1100),
('Mineral Sunscreen SPF 30', 3.5, 'Natural sunscreen with titanium dioxide for daily protection', 35.00, 4.3, 890),
('Repairing Hand Cream', 1.8, 'Intensive hand cream with shea butter and vitamin E', 24.99, 4.6, 750),
('Clarifying Face Mask', 1.2, 'Purifying mask with natural clays for oily and combination skin', 32.50, 4.4, 680),
('Moisturizing Lip Balm', 0.8, 'Hydrating lip balm with beeswax and jojoba oil', 12.99, 4.7, 3200);