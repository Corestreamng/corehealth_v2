<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatConversation extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'is_group'];

    public function participants()
    {
        return $this->hasMany(ChatParticipant::class, 'conversation_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'chat_participants', 'conversation_id', 'user_id')
                    ->withPivot('last_read_at')
                    ->withTimestamps();
    }

    public function latestMessage()
    {
        return $this->hasOne(ChatMessage::class, 'conversation_id')->latest();
    }

    public function archivedBy()
    {
        return $this->belongsToMany(User::class, 'chat_conversation_archives', 'conversation_id', 'user_id')
                    ->withTimestamps()
                    ->withPivot('archived_at');
    }

    public function isArchivedFor($userId)
    {
        return $this->archivedBy()->where('user_id', $userId)->exists();
    }
}
