<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'ticket_number', 'user_id', 'station_id', 'assigned_to',
        'category', 'priority', 'source', 'status', 'subject', 'body', 'resolved_at', 'first_response_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime', 'first_response_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }
}
