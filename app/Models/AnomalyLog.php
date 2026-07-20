<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnomalyLog extends Model
{
    protected $fillable = [
        'audit_log_id',
        'user_id',
        'anomaly_type',
        'level',
        'title',
        'detail',
        'store_name',
        'notified',
    ];

    protected $casts = [
        'notified' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLogs::class, 'audit_log_id');
    }

    // ── Scopes ────────────────────────────────────────────

    public function scopeHigh($query)
    {
        return $query->where('level', 'high');
    }

    public function scopeMedium($query)
    {
        return $query->where('level', 'medium');
    }

    public function scopeUnnotified($query)
    {
        return $query->where('notified', false);
    }

    // ── 等級標籤 ──────────────────────────────────────────

    public function getLevelLabelAttribute(): string
    {
        return match ($this->level) {
            'high'   => '高風險',
            'medium' => '中風險',
            'low'    => '低風險',
            default  => '未知',
        };
    }

    public function getLevelColorAttribute(): string
    {
        return match ($this->level) {
            'high'   => 'red',
            'medium' => 'amber',
            'low'    => 'blue',
            default  => 'gray',
        };
    }
}
