<?php

namespace App\Services;

/**
 * Клас для генерації та аналізу даних IoT датчиків
 * Використовує складну математичну модель для реалістичної емуляції
 */
class IotDataGenerator
{
    /**
     * Генерація нового значення для заводу косметики
     * Імітує реальні процеси: варіння, нагрівання, охолодження, перемішування
     * 
     * @param float|null $currentValue Поточне значення датчика
     * @param float|null $startValue Мінімальне значення (нижня межа)
     * @param float|null $endValue Максимальне значення (верхня межа)
     * @param int $sensorId ID датчика для унікальних параметрів
     * @param int $timestamp Timestamp для синхронізації
     * @param array $history Історія попередніх значень (останні N значень)
     * @param array $otherSensors Дані інших датчиків для залежності
     * @return array ['value' => float, 'is_critical' => bool, 'warning_type' => string|null]
     */
    public function generateNextValue(
        ?float $currentValue = null,
        ?float $startValue = null,
        ?float $endValue = null,
        int $sensorId = 0,
        int $timestamp = 0,
        array $history = [],
        array $otherSensors = []
    ): array {
        if ($timestamp === 0) {
            $timestamp = time();
        }

        // Якщо немає поточного значення, використовуємо середнє між start і end
        if ($currentValue === null) {
            if ($startValue !== null && $endValue !== null) {
                $currentValue = ($startValue + $endValue) / 2;
            } else {
                $currentValue = 0;
            }
        }

        // Визначаємо range
        if ($startValue === null || $endValue === null) {
            $range = abs($currentValue) * 0.1;
            $startValue = $currentValue - $range;
            $endValue = $currentValue + $range;
        }

        $range = $endValue - $startValue;
        $midValue = ($startValue + $endValue) / 2;
        $criticalThreshold = $range * 0.05; // 5% від range - критична зона

        // 1. АНАЛІЗ ІСТОРІЇ - визначаємо тренд та волатильність
        $trendFromHistory = 0;
        $volatility = 0;
        $meanValue = $currentValue;
        if (count($history) >= 3) {
            $recentValues = array_slice($history, -10); // Останні 10 значень
            $trendAnalysis = $this->analyzeTrend($recentValues);
            $trendFromHistory = $trendAnalysis['slope'] * 0.3; // Пом'якшений тренд
            $volatility = $trendAnalysis['volatility'] * 0.4;
            $meanValue = $trendAnalysis['mean'];
        }

        // 2. ПРОЦЕС ВАРІННЯ/НАГРІВАННЯ (для заводу косметики)
        // Імітуємо циклічні процеси нагрівання та охолодження
        $heatingCycle = sin($timestamp * 0.0001 + $sensorId * 0.3) * $range * 0.06; // 6% амплітуда
        $coolingCycle = cos($timestamp * 0.00015 + $sensorId * 0.7) * $range * 0.04; // 4% амплітуда
        
        // 3. ПЕРЕМІШУВАННЯ/ТУРБУЛЕНТНІСТЬ (швидкі коливання)
        $mixingNoise = sin($timestamp * 0.001 + $sensorId * 1.5) * $range * 0.025; // 2.5% амплітуда
        $turbulence = cos($timestamp * 0.0015 + $sensorId * 2.1) * $range * 0.02; // 2% амплітуда

        // 4. ТРЕНД НА ОСНОВІ ІСТОРІЇ (природна еволюція процесу)
        $trend = $trendFromHistory;

        // 5. М'ЯКЕ ПРИТЯГУВАННЯ ДО СЕРЕДНЬОГО (стабілізація процесу)
        $meanReversion = ($meanValue - $currentValue) * 0.015; // 1.5% притягування

        // 6. РЕАЛІСТИЧНИЙ ШУМ ДАТЧИКА (більша рандомність)
        $noiseStdDev = max($range * 0.015, $volatility * 0.6); // 1.5% range або 60% волатильності
        $gaussianNoise = $this->generateGaussianNoise(0, $noiseStdDev);
        
        // Додатковий рандомний шум (характерний для промислових датчиків)
        $randomJitter = (mt_rand(-100, 100) / 100) * $range * 0.01; // ±1% рандом

        // 7. ЗАЛЕЖНІСТЬ ВІД ІНШИХ ДАТЧИКІВ (10-15% вплив)
        $sensorDependency = 0;
        if (!empty($otherSensors)) {
            $totalChange = 0;
            $count = 0;
            foreach ($otherSensors as $otherSensor) {
                if (isset($otherSensor['change']) && isset($otherSensor['value'])) {
                    $otherRange = isset($otherSensor['range']) ? $otherSensor['range'] : $range;
                    $normalizedChange = ($otherSensor['change'] / $otherRange) * $range;
                    $totalChange += $normalizedChange;
                    $count++;
                }
            }
            if ($count > 0) {
                $avgChange = $totalChange / $count;
                // Вплив 10-15% від зміни інших датчиків
                $dependencyFactor = 0.125; // 12.5% в середньому
                $sensorDependency = $avgChange * $dependencyFactor;
            }
        }

        // 8. РІДКІСНІ АНОМАЛІЇ (несподівані події на заводі)
        $anomaly = $this->generateAnomaly($range, $sensorId, $timestamp) * 0.3;

        // 9. АДАПТИВНА КОРЕКЦІЯ (відштовхування від критичних меж)
        $boundaryCorrection = $this->calculateBoundaryCorrection($currentValue, $startValue, $endValue, $range) * 0.3;

        // Об'єднуємо всі компоненти
        $newValue = $currentValue 
            + $heatingCycle
            + $coolingCycle
            + $mixingNoise
            + $turbulence
            + $trend 
            + $meanReversion 
            + $gaussianNoise
            + $randomJitter
            + $sensorDependency
            + $anomaly 
            + $boundaryCorrection;

        // Обмежуємо значення в межах range
        $newValue = $this->clampWithSoftBoundaries($newValue, $startValue, $endValue, $range);

        // Перевірка на критичні значення (5% від меж)
        $distanceToMin = $newValue - $startValue;
        $distanceToMax = $endValue - $newValue;
        $isCritical = false;
        $warningType = null;

        if ($distanceToMin <= $criticalThreshold) {
            $isCritical = true;
            $warningType = 'min'; // Критично низьке значення
        } elseif ($distanceToMax <= $criticalThreshold) {
            $isCritical = true;
            $warningType = 'max'; // Критично високе значення
        }

        return [
            'value' => round($newValue, 2),
            'is_critical' => $isCritical,
            'warning_type' => $warningType,
            'distance_to_min' => round($distanceToMin, 2),
            'distance_to_max' => round($distanceToMax, 2),
            'critical_threshold' => round($criticalThreshold, 2)
        ];
    }

    /**
     * Генерація гаусівського шуму (Box-Muller transform)
     */
    private function generateGaussianNoise(float $mean, float $stdDev): float
    {
        static $spare = null;
        static $hasSpare = false;

        if ($hasSpare) {
            $hasSpare = false;
            return $spare * $stdDev + $mean;
        }

        $hasSpare = true;
        do {
            $u1 = mt_rand() / mt_getrandmax();
            $u2 = mt_rand() / mt_getrandmax();
        } while ($u1 <= 1e-7);

        $mag = $stdDev * sqrt(-2.0 * log($u1));
        $spare = $u2 * 2.0 * M_PI;
        $z0 = $mag * cos($spare);

        return $z0 + $mean;
    }

    /**
     * Генерація аномалій (рідкісні стрибки)
     */
    private function generateAnomaly(float $range, int $sensorId, int $timestamp): float
    {
        // Аномалія з ймовірністю 2%
        if (mt_rand(1, 100) <= 2) {
            $anomalyMagnitude = $range * (mt_rand(-15, 15) / 100); // ±15% від range
            return $anomalyMagnitude;
        }
        return 0;
    }

    /**
     * Розрахунок корекції на основі відстані від меж
     */
    private function calculateBoundaryCorrection(
        float $currentValue,
        float $startValue,
        float $endValue,
        float $range
    ): float {
        $distanceToStart = abs($currentValue - $startValue);
        $distanceToEnd = abs($currentValue - $endValue);
        $minDistance = min($distanceToStart, $distanceToEnd);

        // Якщо значення близько до межі, відштовхуємо його
        if ($minDistance < $range * 0.1) {
            $correctionFactor = ($range * 0.1 - $minDistance) / ($range * 0.1);
            if ($distanceToStart < $distanceToEnd) {
                return $range * 0.02 * $correctionFactor; // Відштовхуємо від нижньої межі
            } else {
                return -$range * 0.02 * $correctionFactor; // Відштовхуємо від верхньої межі
            }
        }

        return 0;
    }

    /**
     * Обмеження значення з м'якими межами (замість різкого обрізання)
     */
    private function clampWithSoftBoundaries(float $value, float $startValue, float $endValue, float $range): float
    {
        $softBoundary = $range * 0.05; // 5% м'яка зона

        if ($value < $startValue) {
            // М'яке відштовхування від нижньої межі
            $excess = $startValue - $value;
            if ($excess > $softBoundary) {
                $value = $startValue + $softBoundary;
            } else {
                $value = $startValue + $excess * 0.3; // Часткове відштовхування
            }
        }

        if ($value > $endValue) {
            // М'яке відштовхування від верхньої межі
            $excess = $value - $endValue;
            if ($excess > $softBoundary) {
                $value = $endValue - $softBoundary;
            } else {
                $value = $endValue - $excess * 0.3; // Часткове відштовхування
            }
        }

        return $value;
    }

    /**
     * Аналіз тренду даних (зростання/спад)
     */
    public function analyzeTrend(array $values): array
    {
        if (count($values) < 2) {
            return ['trend' => 'stable', 'slope' => 0, 'volatility' => 0];
        }

        // Розрахунок нахилу (slope)
        $n = count($values);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        foreach ($values as $index => $value) {
            $x = $index;
            $y = $value;
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);

        // Розрахунок волатильності
        $mean = array_sum($values) / $n;
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        $volatility = sqrt($variance / $n);

        // Визначення тренду
        $trend = 'stable';
        if ($slope > 0.01) {
            $trend = 'increasing';
        } elseif ($slope < -0.01) {
            $trend = 'decreasing';
        }

        return [
            'trend' => $trend,
            'slope' => round($slope, 4),
            'volatility' => round($volatility, 2),
            'mean' => round($mean, 2),
            'min' => round(min($values), 2),
            'max' => round(max($values), 2)
        ];
    }

    /**
     * Виявлення аномалій в історії даних
     */
    public function detectAnomalies(array $values, float $threshold = 2.0): array
    {
        if (count($values) < 3) {
            return [];
        }

        $mean = array_sum($values) / count($values);
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        $stdDev = sqrt($variance / count($values));

        $anomalies = [];
        foreach ($values as $index => $value) {
            $zScore = abs(($value - $mean) / ($stdDev ?: 1));
            if ($zScore > $threshold) {
                $anomalies[] = [
                    'index' => $index,
                    'value' => $value,
                    'z_score' => round($zScore, 2),
                    'deviation' => round($value - $mean, 2)
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Прогнозування наступних значень (простий лінійний прогноз)
     */
    public function predictNextValues(array $values, int $steps = 5): array
    {
        if (count($values) < 2) {
            return array_fill(0, $steps, $values[0] ?? 0);
        }

        $trend = $this->analyzeTrend($values);
        $lastValue = end($values);
        $predictions = [];

        for ($i = 1; $i <= $steps; $i++) {
            $predicted = $lastValue + ($trend['slope'] * $i);
            $predictions[] = round($predicted, 2);
        }

        return $predictions;
    }
}

