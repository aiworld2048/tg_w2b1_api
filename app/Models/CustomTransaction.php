<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'transaction_name',
        'type',
        'amount',
        'before_balance',
        'after_balance',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'before_balance' => 'decimal:4',
        'after_balance' => 'decimal:4',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

