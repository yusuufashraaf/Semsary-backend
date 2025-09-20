<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class CSAgentPropertyAssign extends Model
{
    use HasFactory;

    protected $table = 'cs_agent_property_assigns';

    protected $fillable = [
        'property_id',
        'cs_agent_id',
        'assigned_by',
        'status',
        'notes',
        'metadata',
        'assigned_at',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_REJECTED = 'rejected';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_REJECTED,
        ];
    }

    /**
     * Relationships
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function csAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cs_agent_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Scopes
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeForAgent(Builder $query, int $agentId): Builder
    {
        return $query->where('cs_agent_id', $agentId);
    }

    public function scopeByProperty(Builder $query, int $propertyId): Builder
    {
        return $query->where('property_id', $propertyId);
    }

    public function scopeAssignedBy(Builder $query, int $adminId): Builder
    {
        return $query->where('assigned_by', $adminId);
    }

    public function scopeWithFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['cs_agent_id'] ?? null, function (Builder $q, $agentId) {
                return $q->where('cs_agent_id', $agentId);
            })
            ->when($filters['status'] ?? null, function (Builder $q, $status) {
                if (is_array($status)) {
                    return $q->whereIn('status', $status);
                }
                return $q->where('status', $status);
            })
            ->when($filters['property_id'] ?? null, function (Builder $q, $propertyId) {
                return $q->where('property_id', $propertyId);
            })
            ->when($filters['assigned_by'] ?? null, function (Builder $q, $adminId) {
                return $q->where('assigned_by', $adminId);
            })
            ->when($filters['date_from'] ?? null, function (Builder $q, $dateFrom) {
                return $q->where('assigned_at', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function (Builder $q, $dateTo) {
                return $q->where('assigned_at', '<=', $dateTo);
            })
            ->when($filters['search'] ?? null, function (Builder $q, $search) {
                return $q->whereHas('property', function (Builder $propertyQuery) use ($search) {
                    $propertyQuery->where('title', 'like', "%{$search}%")
                                 ->orWhere('address', 'like', "%{$search}%");
                })
                ->orWhereHas('csAgent', function (Builder $agentQuery) use ($search) {
                    $agentQuery->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%");
                });
            });
    }

    /**
     * Helper Methods
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Status transition methods with validation
     */
    public function startWork(?string $notes = null): bool
    {
        if ($this->isPending()) {
            $this->update([
                'status' => self::STATUS_IN_PROGRESS,
                'started_at' => now(),
                'notes' => $notes ?? $this->notes
            ]);
            return true;
        }
        return false;
    }

    public function complete(?string $notes = null): bool
    {
        if ($this->isInProgress()) {
            $this->update([
                'status' => self::STATUS_COMPLETED,
                'completed_at' => now(),
                'notes' => $notes ?? $this->notes
            ]);
            return true;
        }
        return false;
    }

    public function reject(string $reason): bool
    {
        if ($this->isPending() || $this->isInProgress()) {
            $this->update([
                'status' => self::STATUS_REJECTED,
                'completed_at' => now(),
                'notes' => $reason
            ]);
            return true;
        }
        return false;
    }

    /**
     * Get duration of assignment
     */
    public function getDurationAttribute(): ?int
    {
        if ($this->started_at && $this->completed_at) {
            return $this->started_at->diffInHours($this->completed_at);
        }

        if ($this->started_at) {
            return $this->started_at->diffInHours(now());
        }

        return null;
    }

    /**
     * Get formatted status
     */
    public function getFormattedStatusAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_REJECTED => 'Rejected',
            default => ucfirst($this->status)
        };
    }

    /**
     * Get priority from metadata
     */
    public function getPriorityAttribute(): string
    {
        return $this->metadata['priority'] ?? 'normal';
    }

    /**
     * Set priority in metadata
     */
    public function setPriority(string $priority): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['priority'] = $priority;
        $this->update(['metadata' => $metadata]);
    }
}
