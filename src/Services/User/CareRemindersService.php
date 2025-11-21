<?php

namespace App\Services\User;

class CareRemindersService
{
    /**
     * Отримати всі доступні нагадування
     */
    public static function getAllReminders(): array
    {
        return [
            [
                'text' => 'Refill GlowLab Night Serum',
                'chip' => 'Ships in 4 days',
                'chip_type' => ''
            ],
            [
                'text' => 'Schedule skin diagnostic',
                'chip' => 'Recommended monthly',
                'chip_type' => 'secondary'
            ],
            [
                'text' => 'Redeem loyalty gift box',
                'chip' => 'Available now',
                'chip_type' => 'success'
            ],
            [
                'text' => 'Update your beauty profile',
                'chip' => 'Keep preferences fresh',
                'chip_type' => 'secondary'
            ],
            [
                'text' => 'Check new arrivals',
                'chip' => '5 new products',
                'chip_type' => ''
            ],
            [
                'text' => 'Review your last order',
                'chip' => 'Help others choose',
                'chip_type' => 'secondary'
            ],
            [
                'text' => 'Renew subscription',
                'chip' => 'Expires in 7 days',
                'chip_type' => ''
            ],
            [
                'text' => 'Complete skincare routine quiz',
                'chip' => 'Get personalized tips',
                'chip_type' => 'success'
            ],
            [
                'text' => 'Try new seasonal collection',
                'chip' => 'Limited edition',
                'chip_type' => ''
            ],
            [
                'text' => 'Book consultation call',
                'chip' => 'Free with premium',
                'chip_type' => 'success'
            ],
            [
                'text' => 'Share your routine',
                'chip' => 'Earn bonus points',
                'chip_type' => 'secondary'
            ],
            [
                'text' => 'Check ingredient compatibility',
                'chip' => 'Avoid reactions',
                'chip_type' => ''
            ]
        ];
    }

    /**
     * Отримати 3 випадкові нагадування
     */
    public static function getRandomReminders(int $count = 3): array
    {
        $allReminders = self::getAllReminders();
        shuffle($allReminders);
        return array_slice($allReminders, 0, min($count, count($allReminders)));
    }
}

