<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlotPlayer extends Model
{
   protected $fillable = ['telegram_chat_id', 'balance', 'bonus', 'spins_count'];
}
