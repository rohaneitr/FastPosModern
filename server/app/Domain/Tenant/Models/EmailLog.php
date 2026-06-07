<?php

namespace App\Domain\Tenant\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * EmailLog — immutable record of every outbound email.
 * Written by App\Listeners\LogSentEmail (MessageSent / MessageFailed events).
 * Never update rows — only create them.
 */
class EmailLog extends Model
{
    public const UPDATED_AT = null; // immutable

    protected $fillable = [
        'tenant_id',
        'to_email',
        'subject',
        'status',
        'error_message',
        'mailable_class',
        'sent_at',
    ];

    protected $casts = [
        'sent_at'    => 'datetime',
        'created_at' => 'datetime',
    ];
}
