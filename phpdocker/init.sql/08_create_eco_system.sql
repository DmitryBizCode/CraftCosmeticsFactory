CREATE TABLE IF NOT EXISTS eco_system (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipe_id INT UNSIGNED NOT NULL UNIQUE,
    date_start DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    date_end DATETIME(6),
    staff_id INT UNSIGNED,
    description TEXT,
    is_active BOOLEAN NOT NULL DEFAULT FALSE,

    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON UPDATE CASCADE ON DELETE SET NULL,
    PRIMARY KEY (id)
);

-- Генерація екосистем для кожного рецепту з рандомним призначенням інженерів
-- Інженери мають id 2 (Maria Johnson) та 3 (David Williams)
INSERT INTO eco_system (recipe_id, staff_id, description, is_active) VALUES
(1, 2, 'Monitoring system for Hydrating Face Serum production', FALSE),
(2, 3, 'IoT ecosystem for Anti-Aging Night Cream manufacturing', FALSE),
(3, 2, 'Sensor network for Brightening Day Cream quality control', FALSE),
(4, 3, 'Eco system for Soothing Body Lotion production line', FALSE),
(5, 2, 'Monitoring setup for Nourishing Face Oil formulation', FALSE),
(6, 3, 'IoT sensors for Gentle Cleansing Balm production', FALSE),
(7, 2, 'Quality control system for Mineral Sunscreen SPF 30', FALSE),
(8, 3, 'Monitoring network for Repairing Hand Cream manufacturing', FALSE),
(9, 2, 'IoT ecosystem for Clarifying Face Mask production', FALSE),
(10, 3, 'Sensor system for Moisturizing Lip Balm quality assurance', FALSE);
