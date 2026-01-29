<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\ChatAttachment;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    public function index()
    {
        return view('admin.chat.index');
    }

    public function getConversations(Request $request)
    {
        $userId = Auth::id();
        $searchQuery = $request->get('q');
        $filter = $request->get('filter', 'all'); // all, unread, archived
        $perPage = 15;

        $query = ChatConversation::whereHas('participants', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });

        // Handle archived filter
        if ($filter === 'archived') {
            $query->whereHas('archivedBy', function($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        } else {
            // Exclude archived for other filters
            $query->whereDoesntHave('archivedBy', function($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        }

        // Search Logic
        if ($searchQuery) {
            $query->where(function($q) use ($searchQuery, $userId) {
                // Search by Title (Group chats)
                $q->where('title', 'like', "%{$searchQuery}%")
                  // Search by Participant Name
                  ->orWhereHas('participants.user', function($u) use ($searchQuery, $userId) {
                      $u->where('id', '!=', $userId)
                        ->where(function($nameQ) use ($searchQuery) {
                            $nameQ->where('firstname', 'like', "%{$searchQuery}%")
                                  ->orWhere('surname', 'like', "%{$searchQuery}%");
                        });
                  })
                  // Search by Message Content
                  ->orWhereHas('messages', function($m) use ($searchQuery) {
                      $m->where('body', 'like', "%{$searchQuery}%");
                  });
            });
        }

        // Sort by latest message using subquery
        $latestMessageSubquery = ChatMessage::select('created_at')
            ->whereColumn('conversation_id', 'chat_conversations.id')
            ->latest()
            ->limit(1);

        $query->addSelect(['last_message_at' => $latestMessageSubquery])
              ->orderByDesc('last_message_at');

        $conversations = $query->with(['latestMessage', 'participants.user'])
            ->paginate($perPage);

        // Transform the paginated items
        $conversations->getCollection()->transform(function ($conversation) use ($userId) {
            $otherParticipants = $conversation->participants->where('user_id', '!=', $userId);

            if (!$conversation->is_group) {
                $otherUser = $otherParticipants->first()->user ?? null;
                $conversation->display_name = $otherUser ? $otherUser->firstname . ' ' . $otherUser->surname : 'Unknown User';

                // Avatar Logic
                $colors = ['#007bff', '#6610f2', '#6f42c1', '#e83e8c', '#dc3545', '#fd7e14', '#ffc107', '#28a745', '#20c997', '#17a2b8'];
                if ($otherUser && !empty($otherUser->filename) && file_exists(public_path('storage/image/user/' . $otherUser->filename))) {
                    $conversation->avatar_type = 'image';
                    $conversation->avatar_src = url('storage/image/user/' . $otherUser->filename);
                } else {
                    $conversation->avatar_type = 'initials';
                    $conversation->avatar_initials = $otherUser ? strtoupper(substr($otherUser->firstname ?? '', 0, 1) . substr($otherUser->surname ?? '', 0, 1)) : '??';
                    $conversation->avatar_color = $colors[($otherUser->id ?? 0) % count($colors)];
                }
                $conversation->display_image = $conversation->avatar_type === 'image' ? $conversation->avatar_src : asset('assets/images/faces/face1.jpg');
            } else {
                $conversation->display_name = $conversation->title ?? 'Group Chat';
                $conversation->avatar_type = 'image';
                $conversation->avatar_src = asset('assets/images/group-icon.png');
                $conversation->display_image = $conversation->avatar_src;
            }

            // Add participants list for UI
            $conversation->participants_list = $conversation->participants->map(function($p) {
                $colors = ['#007bff', '#6610f2', '#6f42c1', '#e83e8c', '#dc3545', '#fd7e14', '#ffc107', '#28a745', '#20c997', '#17a2b8'];

                // Handle case where user might be null (deleted user)
                if (!$p->user) {
                    return [
                        'id' => $p->user_id,
                        'name' => 'Deleted User',
                        'avatar_type' => 'initials',
                        'avatar_src' => null,
                        'avatar_initials' => '??',
                        'avatar_color' => $colors[0],
                        'category' => '',
                        'department' => ''
                    ];
                }

                $hasImage = !empty($p->user->filename) && file_exists(public_path('storage/image/user/' . $p->user->filename));

                return [
                    'id' => $p->user->id,
                    'name' => ($p->user->firstname ?? '') . ' ' . ($p->user->surname ?? ''),
                    'avatar_type' => $hasImage ? 'image' : 'initials',
                    'avatar_src' => $hasImage ? url('storage/image/user/' . $p->user->filename) : null,
                    'avatar_initials' => strtoupper(substr($p->user->firstname ?? '', 0, 1) . substr($p->user->surname ?? '', 0, 1)),
                    'avatar_color' => $colors[($p->user->id ?? 0) % count($colors)],
                    'category' => $p->user->category->name ?? '',
                    'department' => $p->user->staff_profile->clinic->name ?? ''
                ];
            });

            $conversation->unread_count = 0; // Implement unread count logic if needed
            return $conversation;
        });

        return response()->json($conversations);
    }

    public function getMessages($conversationId, Request $request)
    {
        $conversation = ChatConversation::findOrFail($conversationId);

        // Check participation
        if (!$conversation->participants()->where('user_id', Auth::id())->exists()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = $conversation->messages()->with(['user.staff_profile', 'attachments']);
        $limit = 20;

        if ($request->has('after_id')) {
            // Polling: Get everything new
            $query->where('id', '>', $request->after_id)->orderBy('created_at', 'asc');
        } elseif ($request->has('before_id')) {
            // Pagination: Get older messages
            $query->where('id', '<', $request->before_id)->orderBy('created_at', 'desc')->take($limit);
        } else {
            // Initial Load: Get latest N messages
            $query->orderBy('created_at', 'desc')->take($limit);
        }

        $messages = $query->get();

        // If we fetched using desc (for pagination/initial), we need to reverse them back to asc for display
        if (!$request->has('after_id')) {
            $messages = $messages->reverse()->values();
        }

        // Transform messages to include sender info
        $messages = $messages->map(function($message) {
            $user = $message->user;
            if ($user) {
                $message->sender_name = ($user->firstname ?? '') . ' ' . ($user->surname ?? '');

                // Get avatar
                if (!empty($user->filename) && file_exists(public_path('storage/image/user/' . $user->filename))) {
                    $message->sender_avatar = url('storage/image/user/' . $user->filename);
                } else {
                    $message->sender_avatar = null;
                }
            } else {
                $message->sender_name = 'Unknown';
                $message->sender_avatar = null;
            }

            return $message;
        });

        return response()->json($messages);
    }

    public function markAsRead($conversationId)
    {
        $conversation = ChatConversation::findOrFail($conversationId);

        // Update last_read_at for the current user
        $conversation->participants()
            ->where('user_id', Auth::id())
            ->update(['last_read_at' => now()]);

        return response()->json(['status' => 'success']);
    }

    public function checkUnread()
    {
        // Simple polling for unread counts or new messages
        // For now, just return total unread count across all conversations
        // This requires tracking 'last_read_at' vs message timestamps

        $userId = Auth::id();
        $unreadCount = ChatMessage::whereHas('conversation.participants', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->where('user_id', '!=', $userId)
        ->whereDoesntHave('conversation.participants', function($q) use ($userId) {
            $q->where('user_id', $userId)
              ->whereColumn('last_read_at', '>=', 'chat_messages.created_at');
        })
        ->count();

        return response()->json(['unread_count' => $unreadCount]);
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|exists:chat_conversations,id',
            'body' => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240', // 10MB max per file
        ]);

        if (!$request->body && !$request->hasFile('attachments')) {
            return response()->json(['error' => 'Message cannot be empty'], 422);
        }

        DB::beginTransaction();
        try {
            $message = ChatMessage::create([
                'conversation_id' => $request->conversation_id,
                'user_id' => Auth::id(),
                'body' => $request->body,
                'type' => $request->hasFile('attachments') ? 'file' : 'text',
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('chat_attachments', 'public');

                    ChatAttachment::create([
                        'message_id' => $message->id,
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'file_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            // Update sender's read status
            $message->conversation->participants()->where('user_id', Auth::id())->update(['last_read_at' => now()]);

            DB::commit();

            $message->load(['user', 'attachments']);
            // No broadcasting needed for polling

            return response()->json($message);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteMessage($messageId)
    {
        $message = ChatMessage::findOrFail($messageId);

        // Only the sender can delete their message
        if ($message->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Soft delete: mark as deleted
        $message->update([
            'deleted_at' => now(),
            'deleted_by' => Auth::id(),
            'body' => null, // Clear body for privacy
        ]);

        return response()->json(['success' => true, 'message' => 'Message deleted']);
    }

    public function archiveConversation($conversationId)
    {
        $conversation = ChatConversation::findOrFail($conversationId);

        // Check if user is participant
        if (!$conversation->participants()->where('user_id', Auth::id())->exists()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Archive for this user only
        DB::table('chat_conversation_archives')->updateOrInsert(
            ['conversation_id' => $conversationId, 'user_id' => Auth::id()],
            ['archived_at' => now(), 'updated_at' => now()]
        );

        return response()->json(['success' => true, 'message' => 'Conversation archived']);
    }

    public function unarchiveConversation($conversationId)
    {
        DB::table('chat_conversation_archives')
            ->where('conversation_id', $conversationId)
            ->where('user_id', Auth::id())
            ->delete();

        return response()->json(['success' => true, 'message' => 'Conversation unarchived']);
    }

    public function createConversation(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'title' => 'nullable|string',
        ]);

        $userIds = $validated['user_ids'];
        $userIds[] = Auth::id();
        $userIds = array_unique($userIds);

        if (count($userIds) < 2) {
            return response()->json(['error' => 'At least 2 participants required'], 422);
        }

        // Check for existing 1-on-1 conversation
        if (count($userIds) == 2) {
            $otherUserId = $validated['user_ids'][0];
            $existing = ChatConversation::where('is_group', false)
                ->whereHas('participants', function($q) use ($otherUserId) {
                    $q->where('user_id', $otherUserId);
                })
                ->whereHas('participants', function($q) {
                    $q->where('user_id', Auth::id());
                })
                ->first();

            if ($existing) {
                return response()->json($existing);
            }
        }

        DB::beginTransaction();
        try {
            $conversation = ChatConversation::create([
                'title' => $validated['title'] ?? null,
                'is_group' => count($userIds) > 2,
            ]);

            foreach ($userIds as $id) {
                $conversation->participants()->create(['user_id' => $id]);
            }

            DB::commit();

            // Load the conversation with relationships for proper display
            $conversation->load(['participants.user', 'latestMessage']);

            return response()->json($conversation);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Conversation creation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create conversation'], 500);
        }
    }

    public function searchUsers(Request $request)
    {
        $query = $request->get('q');

        // Use Staff model as requested
        $staffMembers = \App\Models\Staff::with(['user.category', 'clinic', 'specialization'])
            ->whereHas('user', function($q) use ($query) {
                $q->where('id', '!=', Auth::id())
                  ->where(function($subQ) use ($query) {
                      $subQ->where('firstname', 'like', "%{$query}%")
                           ->orWhere('surname', 'like', "%{$query}%")
                           ->orWhere('email', 'like', "%{$query}%");
                  });
            })
            ->limit(10)
            ->get();

        $results = $staffMembers->map(function ($staff) {
            return [
                'id' => $staff->user->id, // We still need the User ID for the chat system
                'firstname' => $staff->user->firstname,
                'surname' => $staff->user->surname,
                'filename' => $staff->user->filename,
                'user_category' => $staff->user->category->name ?? 'Staff',
                'specialization' => $staff->specialization->name ?? 'General',
                'department' => $staff->clinic->name ?? 'General',
            ];
        });

        return response()->json($results);
    }

    public function searchMessagesInConversation($conversationId, Request $request)
    {
        $conversation = ChatConversation::findOrFail($conversationId);
        $searchQuery = $request->get('q', '');

        // Check participation
        if (!$conversation->participants()->where('user_id', Auth::id())->exists()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (strlen($searchQuery) < 2) {
            return response()->json([]);
        }

        // Search messages in this conversation
        $messages = $conversation->messages()
            ->where('body', 'like', "%{$searchQuery}%")
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function($msg) {
                return [
                    'id' => $msg->id,
                    'body' => $msg->body,
                    'created_at' => $msg->created_at,
                    'user_id' => $msg->user_id
                ];
            });

        return response()->json($messages);
    }
}
