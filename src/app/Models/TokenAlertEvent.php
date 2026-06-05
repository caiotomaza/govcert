<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenAlertEvent extends Model
{
    protected $fillable = [
        'token_alert_id',
        'user_id',
        'current_tokens',
        'threshold_tokens',
        'triggered_at',
        'notified_targets',
    ];

    protected $casts = [
        'triggered_at' => 'datetime',
        'notified_targets' => 'array',
        'current_tokens' => 'integer',
        'threshold_tokens' => 'integer',
    ];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(TokenAlert::class, 'token_alert_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
