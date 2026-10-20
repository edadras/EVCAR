<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    protected $fillable = [
        'support_ticket_id', 'user_id', 'message', 'is_staff_reply', 'is_internal', 'attachments',
    ];

    protected function casts(): array
    {
        return ['attachments' => 'array', 'is_staff_reply' => 'boolean', 'is_internal' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
