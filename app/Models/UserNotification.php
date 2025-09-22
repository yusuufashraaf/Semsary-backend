<?php

namespace App\Models;

use App\Enums\NotificationPurpose;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    use HasFactory;

    protected $table = 'user_notifications';

    protected $fillable = [
        'user_id',      // recipient
        'sender_id',    // who triggered the notification (CORRECT - matches DB)
        'entity_id',    // related entity (property, booking, etc.)
        'purpose',      // enum NotificationPurpose
        'title',
        'message',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'purpose' => NotificationPurpose::class, // enum casting
    ];

    /**
     * Recipient (who receives the notification).
     */
    public function recipient()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Sender (who triggered the notification).
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');  // CORRECT - matches DB
    }

    /**
     * Scope: filter notifications by purpose.
     */
    public function scopeByPurpose($query, NotificationPurpose $purpose)
    {
        return $query->where('purpose', $purpose);
    }

    /**
     * Mark as read.
     */
    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }

    /**
     * Mark as unread.
     */
    public function markAsUnread(): void
    {
        $this->update(['is_read' => false]);
    }
}