CREATE TABLE IF NOT EXISTS recipe_product (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quantity INT NOT NULL,
    recipe_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,

    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE CASCADE,
    PRIMARY KEY (id)
);

-- Генерація зв'язків між рецептами та продуктами
-- Recipe 1: Hydrating Face Serum (продукти 1, 5)
INSERT INTO recipe_product (recipe_id, product_id, quantity) VALUES
(1, 1, 50),  -- Hyaluronic Acid
(1, 5, 100); -- Aloe Vera Extract

-- Recipe 2: Anti-Aging Night Cream (продукти 2, 3, 4)
INSERT INTO recipe_product (recipe_id, product_id, quantity) VALUES
(2, 2, 30),  -- Retinol
(2, 3, 50),  -- Vitamin C
(2, 4, 200); -- Shea Butter

-- Recipe 3: Brightening Day Cream (продукти 3, 4, 5)
INSERT INTO recipe_product (recipe_id, product_id, quantity) VALUES
(3, 3, 80),  -- Vitamin C
(3, 4, 150), -- Shea Butter
(3, 5, 75);  -- Aloe Vera Extract

-- Recipe 4: Soothing Body Lotion (продукти 4, 5, 8)
INSERT INTO recipe_product (recipe_id, product_id, quantity) VALUES
(4, 4, 300), -- Shea Butter
(4, 5, 150), -- Aloe Vera Extract
(4, 8, 100); -- Jojoba Oil

-- Recipe 5: Nourishing Face Oil (продукти 7, 8)
INSERT INTO recipe_product (recipe_id, product_id, quantity) VALUES
(5, 7, 150), -- Coconut Oil
(5, 8, 100); -- Jojoba Oil

-- Recipe 6: Gentle Cleansing Balm (продукти 7, 9)
INSERT INTO recipe_product (recipe_id, product_id, quantity) VALUES
(6, 7, 200), -- Coconut Oil
(6, 9, 150); -- Beeswax

-- Recipe 7: Mineral Sunscreen SPF 30 (продукти 9, 10)
INSERT INTO recipe_product (recipe_id, product_id, quantity) VALUES
(7, 9, 100), -- Beeswax
(7, 10, 80); -- Titanium Dioxide

-- Recipe 8: Repairing Hand Cream (продукти 4, 9)
INSERT INTO recipe_product (recipe_id, product_id, quantity) VALUES
(8, 4, 250), -- Shea Butter
(8, 9, 100); -- Beeswax

-- Recipe 9: Clarifying Face Mask (продукти 3, 5)
INSERT INTO recipe_product (recipe_id, product_id, quantity) VALUES
(9, 3, 60),  -- Vitamin C
(9, 5, 120); -- Aloe Vera Extract

-- Recipe 10: Moisturizing Lip Balm (продукти 7, 8, 9)
INSERT INTO recipe_product (recipe_id, product_id, quantity) VALUES
(10, 7, 50),  -- Coconut Oil
(10, 8, 30),  -- Jojoba Oil
(10, 9, 40);  -- Beeswax