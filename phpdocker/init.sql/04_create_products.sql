CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    quantity FLOAT NOT NULL,
    unit ENUM('g', 'kg', 'ml', 'l', 'pcs') NOT NULL DEFAULT 'g',
    temperature FLOAT,
    lightening FLOAT,
    description TEXT,
    allergen BOOLEAN NOT NULL DEFAULT FALSE,

    PRIMARY KEY (id)
);

-- Генерація тестових даних для продуктів
INSERT INTO products (name, quantity, unit, temperature, lightening, description, allergen) VALUES
('Hyaluronic Acid', 50.0, 'ml', 20.0, 0.0, 'High-quality hyaluronic acid for hydration', FALSE),
('Retinol', 30.0, 'ml', 15.0, 0.0, 'Pure retinol for anti-aging formulations', FALSE),
('Vitamin C', 100.0, 'g', 18.0, 0.0, 'L-ascorbic acid powder for brightening', TRUE),
('Shea Butter', 500.0, 'g', 25.0, 0.0, 'Organic shea butter for moisturizing', FALSE),
('Aloe Vera Extract', 200.0, 'ml', 20.0, 0.0, 'Concentrated aloe vera for soothing', FALSE),
('Peanut Oil', 250.0, 'ml', 22.0, 0.0, 'Cold-pressed peanut oil', TRUE),
('Coconut Oil', 300.0, 'ml', 24.0, 0.0, 'Organic virgin coconut oil', FALSE),
('Jojoba Oil', 150.0, 'ml', 20.0, 0.0, 'Pure jojoba oil for skin care', FALSE),
('Beeswax', 200.0, 'g', 30.0, 0.0, 'Natural beeswax for texture', FALSE),
('Titanium Dioxide', 50.0, 'g', 20.0, 85.0, 'Mineral sunscreen ingredient', FALSE);