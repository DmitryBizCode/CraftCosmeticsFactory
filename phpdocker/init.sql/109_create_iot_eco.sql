CREATE TABLE IF NOT EXISTS iot_eco (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  iot_id INT UNSIGNED NOT NULL,
  eco_system_id INT UNSIGNED NOT NULL,
  critical_warnings_count INT UNSIGNED NOT NULL DEFAULT 0,

  FOREIGN KEY (iot_id) REFERENCES iot(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (eco_system_id) REFERENCES eco_system(id) ON UPDATE CASCADE ON DELETE CASCADE,
  PRIMARY KEY (id)
);

-- Генерація IoT датчиків для кожної екосистеми (2-3 датчики на екосистему)
-- IoT датчики мають id від 1 до 6
INSERT INTO iot_eco (iot_id, eco_system_id, critical_warnings_count) VALUES
-- Екосистема 1 (Recipe 1 - Hydrating Face Serum)
(1, 1, 0), (2, 1, 0), (3, 1, 0),
-- Екосистема 2 (Recipe 2 - Anti-Aging Night Cream)
(1, 2, 0), (4, 2, 0), (5, 2, 0),
-- Екосистема 3 (Recipe 3 - Brightening Day Cream)
(2, 3, 0), (3, 3, 0), (6, 3, 0),
-- Екосистема 4 (Recipe 4 - Soothing Body Lotion)
(1, 4, 0), (2, 4, 0),
-- Екосистема 5 (Recipe 5 - Nourishing Face Oil)
(3, 5, 0), (4, 5, 0), (5, 5, 0),
-- Екосистема 6 (Recipe 6 - Gentle Cleansing Balm)
(1, 6, 0), (6, 6, 0),
-- Екосистема 7 (Recipe 7 - Mineral Sunscreen SPF 30)
(2, 7, 0), (3, 7, 0), (5, 7, 0),
-- Екосистема 8 (Recipe 8 - Repairing Hand Cream)
(1, 8, 0), (4, 8, 0),
-- Екосистема 9 (Recipe 9 - Clarifying Face Mask)
(2, 9, 0), (3, 9, 0), (6, 9, 0),
-- Екосистема 10 (Recipe 10 - Moisturizing Lip Balm)
(1, 10, 0), (5, 10, 0);