<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\UserNotification;
use App\Models\User;

class UserNotificationSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            // Create 3-5 notifications per user
            $count = rand(3, 5);
            
            for ($i = 0; $i < $count; $i++) {
                UserNotification::create([
                    'user_id' => $user->id,
                    'title' => $this->getRandomTitle(),
                    'message' => $this->getRandomMessage(),
                    'is_read' => rand(0, 1) // Random read status
                ]);
            }
        }
    }

    private function getRandomTitle(): string
    {
        $titles = [
            'Welcome to our platform!',
            'New message received',
            'Booking confirmed',
            'Payment successful',
            'Profile updated',
            'Reminder',
            'Important notice'
        ];

        return $titles[array_rand($titles)];
    }

    private function getRandomMessage(): string
    {
        $messages = [
            'Thank you for joining our service.',
            'You have a new message in your inbox.',
            'Your booking has been confirmed successfully.',
            'Your payment was processed successfully.',
            'Your profile information has been updated.',
            'Don\'t forget to complete your profile.',
            'Please review our terms and conditions.'
        ];

        return $messages[array_rand($messages)];
    }
}