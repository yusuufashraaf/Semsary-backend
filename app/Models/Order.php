<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'payment_status',
        'total_amount',
        'currency',
        'items',
        'billing_data',
        'shipping_data',
        'notes',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'items' => 'array',
        'billing_data' => 'array',
        'shipping_data' => 'array',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [];

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (!$order->order_number) {
                $order->order_number = $order->generateOrderNumber();
            }
        });
    }

    /**
     * Generate unique order number
     */
    protected function generateOrderNumber()
    {
        $prefix = 'ORD';
        $timestamp = now()->format('Ymd');

        // Get the last order for today
        $lastOrder = static::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastOrder ? (int) substr($lastOrder->order_number, -4) + 1 : 1;

        return $prefix . $timestamp . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get the user that owns the order.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payments for the order.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the latest payment for the order.
     */
    public function latestPayment()
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /**
     * Get the successful payment for the order.
     */
    public function successfulPayment()
    {
        return $this->hasOne(Payment::class)->where('status', Payment::STATUS_COMPLETED);
    }

    /**
     * Scope for orders with specific status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for orders with specific payment status
     */
    public function scopeWithPaymentStatus($query, $paymentStatus)
    {
        return $query->where('payment_status', $paymentStatus);
    }

    /**
     * Scope for paid orders
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * Scope for pending orders
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for completed orders
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for cancelled orders
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Check if order is paid
     */
    public function isPaid()
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Check if order is pending payment
     */
    public function isPendingPayment()
    {
        return $this->payment_status === 'pending';
    }

    /**
     * Check if order is cancelled
     */
    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if order is completed
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Mark order as paid
     */
    public function markAsPaid()
    {
        $this->update([
            'payment_status' => 'paid',
            'paid_at' => now()
        ]);
    }

    /**
     * Mark order as shipped
     */
    public function markAsShipped()
    {
        $this->update([
            'status' => 'shipped',
            'shipped_at' => now()
        ]);
    }

    /**
     * Mark order as delivered
     */
    public function markAsDelivered()
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now()
        ]);
    }

    /**
     * Cancel the order
     */
    public function cancel($reason = null)
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'notes' => $this->notes ? $this->notes . "\nCancellation reason: " . $reason : "Cancellation reason: " . $reason
        ]);
    }

    /**
     * Calculate total items quantity
     */
    public function getTotalQuantityAttribute()
    {
        if (!$this->items || !is_array($this->items)) {
            return 0;
        }

        return collect($this->items)->sum('quantity');
    }

    /**
     * Get formatted total amount
     */
    public function getFormattedTotalAttribute()
    {
        return number_format($this->total_amount, 2) . ' ' . $this->currency;
    }

    /**
     * Get order status badge color
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'warning',
            'processing' => 'info',
            'shipped' => 'primary',
            'delivered' => 'success',
            'cancelled' => 'danger',
            'refunded' => 'secondary',
            default => 'secondary'
        };
    }

    /**
     * Get payment status badge color
     */
    public function getPaymentStatusColorAttribute()
    {
        return match($this->payment_status) {
            'pending' => 'warning',
            'paid' => 'success',
            'failed' => 'danger',
            'refunded' => 'info',
            default => 'secondary'
        };
    }

    /**
     * Get customer full name from billing data
     */
    public function getCustomerNameAttribute()
    {
        if (!$this->billing_data) {
            return $this->user->name ?? 'Unknown';
        }

        $firstName = $this->billing_data['first_name'] ?? '';
        $lastName = $this->billing_data['last_name'] ?? '';

        return trim($firstName . ' ' . $lastName) ?: ($this->user->name ?? 'Unknown');
    }

    /**
     * Get customer email from billing data
     */
    public function getCustomerEmailAttribute()
    {
        return $this->billing_data['email'] ?? $this->user->email ?? null;
    }

    /**
     * Get customer phone from billing data
     */
    public function getCustomerPhoneAttribute()
    {
        return $this->billing_data['phone_number'] ?? $this->user->phone ?? null;
    }
}
