<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    protected $fillable = ['ai_conversation_id', 'role', 'content', 'display_text', 'tools_used', 'source'];

    protected function casts(): array
    {
        return ['content' => 'array', 'tools_used' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
