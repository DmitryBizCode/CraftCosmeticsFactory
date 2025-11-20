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
        $maxChangeAllowed = $range * 0.05; // МАКСИМУМ 5% від range - максимальна зміна

        // Визначаємо поточне положення відносно меж (в процентах)
        $distanceFromStart = $currentValue - $startValue;
        $distanceFromEnd = $endValue - $currentValue;
        $percentFromStart = ($distanceFromStart / $range) * 100; // Від 0 до 100%
        $percentFromEnd = ($distanceFromEnd / $range) * 100; // Від 0 до 100%

        // Зони балансування
        $safeZoneMin = 10; // 10% від початку - нижня межа безпечної зони
        $safeZoneMax = 90; // 90% від початку - верхня межа безпечної зони
        $criticalZone = 5; // 5% від меж - критична зона

        // 1. ОСНОВНЕ КОЛИВАННЯ (у межах норми)
        // Невеликі синусоїдальні коливання для природного руху
        // Додаємо випадковість в напрямок коливань
        $oscillationDirection = mt_rand(0, 1) ? 1 : -1; // Випадковий напрямок
        $baseOscillation = sin($timestamp * 0.0001 + $sensorId * 0.3) * $maxChangeAllowed * 0.6 * $oscillationDirection; // До 60% від maxChangeAllowed
        $secondaryOscillation = cos($timestamp * 0.00015 + $sensorId * 0.7) * $maxChangeAllowed * 0.4 * (mt_rand(0, 1) ? 1 : -1); // До 40% з випадковим напрямком

        // 2. БАЛАНСУВАННЯ (рух до протилежної сторони при наближенні до критичної зони)
        $balancingForce = 0;
        if ($percentFromStart <= $criticalZone) {
            // Дуже близько до нижньої межі - рухаємося вгору (до центру)
            $urgency = ($criticalZone - $percentFromStart) / $criticalZone; // 0 до 1
            $balancingForce = $maxChangeAllowed * 0.9 * $urgency; // До 90% від максимальної зміни
        } elseif ($percentFromEnd <= $criticalZone) {
            // Дуже близько до верхньої межі - рухаємося вниз (до центру)
            $urgency = ($criticalZone - $percentFromEnd) / $criticalZone; // 0 до 1
            $balancingForce = -$maxChangeAllowed * 0.9 * $urgency; // До 90% від максимальної зміни
        } elseif ($percentFromStart <= $safeZoneMin) {
            // Близько до нижньої безпечної межі - помірний рух вгору
            $urgency = ($safeZoneMin - $percentFromStart) / $safeZoneMin; // 0 до 1
            $balancingForce = $maxChangeAllowed * 0.6 * $urgency; // До 60% від максимальної зміни
        } elseif ($percentFromEnd <= (100 - $safeZoneMax)) {
            // Близько до верхньої безпечної межі - помірний рух вниз
            $urgency = ((100 - $safeZoneMax) - $percentFromEnd) / (100 - $safeZoneMax); // 0 до 1
            $balancingForce = -$maxChangeAllowed * 0.6 * $urgency; // До 60% від максимальної зміни
        } else {
            // В безпечній зоні (10-90%) - можливі невеликі коливання в обидві сторони
            // З шансом 5% дозволяємо рух до критичної зони
            if (mt_rand(1, 100) <= 5) {
                // Дозволяємо випадковий рух (може бути в напрямку до критичної зони)
                $balancingForce = (mt_rand(-100, 100) / 100) * $maxChangeAllowed * 0.5;
            } else {
                // Звичайне коливання в безпечній зоні
                $balancingForce = (mt_rand(-50, 50) / 100) * $maxChangeAllowed * 0.3;
            }
        }

        // 3. М'ЯКЕ ПРИТЯГУВАННЯ ДО ЦЕНТРУ (якщо не в критичній зоні)
        $meanReversion = 0;
        if ($percentFromStart > $criticalZone && $percentFromEnd > $criticalZone) {
            // Тільки якщо не в критичній зоні - притягування до центру
            $distanceFromCenter = abs($currentValue - $midValue);
            if ($distanceFromCenter > $range * 0.2) { // Якщо далеко від центру
                $meanReversion = ($midValue - $currentValue) * 0.002; // Дуже м'яке притягування
                // Обмежуємо притягування до 0.5% від range
                if (abs($meanReversion) > $maxChangeAllowed * 0.5) {
                    $meanReversion = ($meanReversion > 0 ? 1 : -1) * $maxChangeAllowed * 0.5;
                }
            }
        }

        // 4. ЗАЛЕЖНІСТЬ ВІД ІНШИХ ДАТЧИКІВ (25% вплив від зміни інших датчиків)
        $sensorDependency = 0;
        if (!empty($otherSensors)) {
            $totalChange = 0;
            $count = 0;
            foreach ($otherSensors as $otherSensor) {
                if (isset($otherSensor['change']) && isset($otherSensor['value'])) {
                    $otherRange = isset($otherSensor['range']) ? $otherSensor['range'] : $range;
                    // Нормалізуємо зміну іншого датчика
                    $normalizedChange = ($otherSensor['change'] / $otherRange) * $range;
                    $totalChange += $normalizedChange;
                    $count++;
                }
            }
            if ($count > 0) {
                $avgChange = $totalChange / $count;
                // Вплив 25% від зміни інших датчиків (рандомно в + або -)
                $direction = mt_rand(0, 1) ? 1 : -1;
                $sensorDependency = $avgChange * 0.25 * $direction;
                // Обмежуємо до максимальної зміни
                if (abs($sensorDependency) > $maxChangeAllowed) {
                    $sensorDependency = ($sensorDependency > 0 ? 1 : -1) * $maxChangeAllowed;
                }
            }
        }

        // 5. НЕЗНАЧНИЙ РАНДОМНИЙ ШУМ (до 30% від maxChangeAllowed)
        $randomJitter = (mt_rand(-100, 100) / 100) * $maxChangeAllowed * 0.3;

        // 6. ІМПУЛЬСИВНІ СТРИБКИ (з ймовірністю 5% стрибок на 10% від range)
        $impulsiveJump = 0;
        if (mt_rand(1, 100) <= 5) {
            // З ймовірністю 5% робимо імпульсивний стрибок на 10% від range
            $jumpMagnitude = $range * 0.10; // 10% від range
            $jumpDirection = mt_rand(0, 1) ? 1 : -1; // Випадковий напрямок
            $impulsiveJump = $jumpMagnitude * $jumpDirection;
        }

        // Об'єднуємо всі компоненти
        $totalChange = $baseOscillation 
            + $secondaryOscillation 
            + $balancingForce 
            + $meanReversion 
            + $sensorDependency
            + $randomJitter
            + $impulsiveJump;

        // КРИТИЧНО: Обмежуємо загальну зміну до максимум 5% від range (але імпульсивні стрибки можуть бути більшими)
        // Якщо це не імпульсивний стрибок, обмежуємо до maxChangeAllowed
        if ($impulsiveJump == 0 && abs($totalChange) > $maxChangeAllowed) {
            $totalChange = ($totalChange > 0 ? 1 : -1) * $maxChangeAllowed;
        }
        // Для імпульсивних стрибків дозволяємо до 10% від range, але обмежуємо в межах range

        $newValue = $currentValue + $totalChange;

        // Обмежуємо значення в межах range
        $newValue = max($startValue, min($endValue, $newValue));

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

