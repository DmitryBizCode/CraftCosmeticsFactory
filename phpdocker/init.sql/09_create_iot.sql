CREATE TABLE IF NOT EXISTS iot (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    start_value FLOAT NOT NULL, 
    end_value FLOAT NOT NULL,
    description TEXT,

    PRIMARY KEY (id)
);

-- Генерація тестових даних для IoT сенсорів
INSERT INTO iot (name, start_value, end_value, description) VALUES
('Temperature Sensor 1', 18.0, 25.0, 'Monitors ambient temperature in production area'),
('Humidity Sensor', 40.0, 60.0, 'Tracks humidity levels for optimal product storage'),
('Pressure Sensor', 0.8, 1.2, 'Measures atmospheric pressure in mixing chambers'),
('pH Level Sensor', 5.5, 7.5, 'Controls acidity levels in cosmetic formulations'),
('Light Intensity Sensor', 200.0, 800.0, 'Monitors light exposure for photosensitive ingredients'),
('Viscosity Sensor', 100.0, 500.0, 'Measures product consistency during mixing');