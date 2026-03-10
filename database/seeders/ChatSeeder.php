<?php

namespace Database\Seeders;

use App\Models\Chat;
use App\Models\Message;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChatSeeder extends Seeder
{
    public function run()
    {
        // Get some users and properties
        $owners = User::where('role', 'owner')->take(3)->get();
        $renters = User::where('role', 'renter')->take(3)->get();
        $properties = Property::take(5)->get();

        foreach ($properties as $property) {
            foreach ($renters as $renter) {
                // Create chat
                $chat = Chat::create([
                    'property_id' => $property->id,
                    'owner_id' => $property->user_id,
                    'renter_id' => $renter->id,
                    'last_message_at' => now()
                ]);

                // Create some sample messages
                $messages = [
                    [
                        'sender_id' => $renter->id,
                        'content' => 'Hi, I\'m interested in your property. Is it still available?',
                        'is_read' => true,
                        'created_at' => now()->subDays(2)
                    ],
                    [
                        'sender_id' => $property->user_id,
                        'content' => 'Yes, it\'s still available! Would you like to schedule a viewing?',
                        'is_read' => true,
                        'created_at' => now()->subDays(1)
                    ],
                    [
                        'sender_id' => $renter->id,
                        'content' => 'That would be great! When are you available?',
                        'is_read' => false,
                        'created_at' => now()->subHours(3)
                    ]
                ];

                foreach ($messages as $messageData) {
                    Message::create(array_merge($messageData, ['chat_id' => $chat->id]));
                }
            }
        }
    }
}