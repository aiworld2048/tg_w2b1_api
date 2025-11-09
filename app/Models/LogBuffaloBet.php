<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogBuffaloBet extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_account',
        'player_id',
        'player_agent_id',
        'buffalo_game_id',
        'request_time',
        'bet_amount',
        'win_amount',
        'payload',
        'game_name',
        'status',
        'before_balance',
        'balance',
    ];

    protected $casts = [
        'request_time' => 'datetime',
        'bet_amount' => 'decimal:4',
        'win_amount' => 'decimal:4',
        'before_balance' => 'decimal:4',
        'balance' => 'decimal:4',
        'payload' => 'json',
    ];

    /**
     * Get the player user
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_id');
    }

    /**
     * Get the agent user
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_agent_id');
    }

    /**
     * Scope for successful transactions
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Get net profit/loss for this bet
     */
    public function getNetAmountAttribute(): float
    {
        return (float) $this->win_amount - (float) $this->bet_amount;
    }
}
