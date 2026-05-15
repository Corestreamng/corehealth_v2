<?php
/**
 * ChatMessageObserver - Cleanup logic for group chat messages.
 */

namespace App\Observers;

use App\Models\ChatMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ChatMessageObserver
{
    /**
     * Handle the ChatMessage "created" event.
     *
     * @param  \App\Models\ChatMessage  $chatMessage
     * @return void
     */
    public function created(ChatMessage $chatMessage)
    {
        // We only clean up group chats
        if ($chatMessage->conversation && $chatMessage->conversation->is_group) {
            $this->cleanupOldMessages($chatMessage->conversation_id);
        }
    }

    /**
     * Clean up messages older than 7 days in a specific conversation.
     *
     * @param int $conversationId
     * @return void
     */
    protected function cleanupOldMessages($conversationId)
    {
        try {
            $cutoff = Carbon::now()->subDays(7);

            // Find old messages
            $oldMessagesQuery = ChatMessage::where('conversation_id', $conversationId)
                ->where('created_at', '<', $cutoff);

            $count = $oldMessagesQuery->count();

            if ($count > 0) {
                // Perform the cleanup
                // Note: We use delete() on the query builder for efficiency.
                // Since ChatMessage doesn't have a deleted() observer method that handles file deletion,
                // and it doesn't use SoftDeletes trait, this will be a hard delete.
                $oldMessagesQuery->delete();

                Log::info("ChatMessageObserver: Cleaned up {$count} messages older than 7 days in conversation ID: {$conversationId}");
            }
        } catch (\Exception $e) {
            Log::error("ChatMessageObserver: Error during cleanup: " . $e->getMessage());
        }
    }
}
