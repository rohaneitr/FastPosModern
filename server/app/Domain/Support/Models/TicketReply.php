<?php

namespace App\Domain\Support\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domain\IAM\Models\User;

class TicketReply extends Model
{
    protected $fillable = ['ticket_id', 'user_id', 'message'];

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
