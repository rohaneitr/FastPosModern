<?php

namespace App\Domain\Support\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domain\IAM\Models\User;
use App\Domain\Tenant\Models\Business;

class SupportTicket extends Model
{
    protected $fillable = ['business_id', 'user_id', 'subject', 'status', 'priority'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function replies()
    {
        return $this->hasMany(TicketReply::class, 'ticket_id');
    }
}
