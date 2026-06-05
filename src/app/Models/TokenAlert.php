<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TokenAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'threshold_tokens',
        'period',
        'notify_user',
        'notify_auditors',
        'notify_admins',
        'notify_email',
        'is_active',
        'created_by',
        'last_triggered_at',
    ];

    protected $casts = [
        'notify_user' => 'boolean',
        'notify_auditors' => 'boolean',
        'notify_admins' => 'boolean',
        'is_active' => 'boolean',
        'threshold_tokens' => 'integer',
        'last_triggered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TokenAlertEvent::class);
    }

    public function getPeriodLabelAttribute(): string
    {
        return match ($this->period) {
            'daily' => 'Diário',
            'weekly' => 'Semanal',
            'monthly' => 'Mensal',
            'total' => 'Total acumulado',
            default => $this->period,
        };
    }
}
