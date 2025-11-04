<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramUser extends Model
{
    protected $fillable = [
        'chat_id', 'username', 'first_name', 'last_name', 'language_code', 'last_active_at'
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class, 'user_id');
    }
}
