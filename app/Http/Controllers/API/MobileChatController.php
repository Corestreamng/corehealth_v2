<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ChatAttachment;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MobileChatController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    //  SHARED: Conversations, Messages, Send, Read, Delete
    //  (Used by both Doctor & Patient apps)
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/mobile/{role}/chat/conversations
     * Lists conversations for the authenticated user.
     * For patients, only shows conversations with their encounter doctors.
     */
    public function getConversations(Request $request)
    {
        $userId = Auth::id();
        $filter = $request->get('filter', 'all');
        $search = $request->get('q');

        $query = ChatConversation::whereHas('participants', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });

        // Patient restriction: only show conversations where the other user is one of their doctors
        if ($this->isPatientUser()) {
            $doctorUserIds = $this->getPatientDoctorUserIds();
            $query->whereHas('participants', function ($q) use ($doctorUserIds, $userId) {
                $q->where('user_id', '!=', $userId)
                  ->whereIn('user_id', $doctorUserIds);
            });
        }

        if ($filter === 'archived') {
            $query->whereHas('archivedBy', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        } else {
            $query->whereDoesntHave('archivedBy', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        }

        if ($filter === 'unread') {
            $query->whereHas('messages', function ($mq) use ($userId) {
                $mq->where('user_id', '!=', $userId)
                    ->where(function ($sub) use ($userId) {
                        $sub->whereHas('conversation.participants', function ($pq) use ($userId) {
                            $pq->where('user_id', $userId)
                               ->where(function ($inner) {
                                   $inner->whereNull('last_read_at')
                                         ->orWhereColumn('chat_messages.created_at', '>', 'chat_participants.last_read_at');
                               });
                        });
                    });
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search, $userId) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('participants.user', function ($u) use ($search, $userId) {
                      $u->where('id', '!=', $userId)
                        ->where(function ($n) use ($search) {
                            $n->where('firstname', 'like', "%{$search}%")
                              ->orWhere('surname', 'like', "%{$search}%");
                        });
                  });
            });
        }

        // Sort by latest message
        $latestSub = ChatMessage::select('created_at')
            ->whereColumn('conversation_id', 'chat_conversations.id')
            ->latest()
            ->limit(1);

        $query->addSelect(['*', 'last_message_at' => $latestSub])
              ->orderByDesc('last_message_at');

        $conversations = $query->with(['latestMessage.user', 'participants.user'])
            ->paginate(20);

        $conversations->getCollection()->transform(function ($conv) use ($userId) {
            return $this->transformConversation($conv, $userId);
        });

        return response()->json($conversations);
    }

    /**
     * GET /api/mobile/{role}/chat/messages/{conversationId}
     */
    public function getMessages($conversationId, Request $request)
    {
        $conv = ChatConversation::findOrFail($conversationId);

        if (!$conv->participants()->where('user_id', Auth::id())->exists()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = $conv->messages()->with(['user', 'attachments']);
        $limit = 30;

        if ($request->has('after_id')) {
            $query->where('id', '>', $request->after_id)->orderBy('id', 'asc');
        } elseif ($request->has('before_id')) {
            $query->where('id', '<', $request->before_id)->orderBy('id', 'desc')->take($limit);
        } else {
            $query->orderBy('id', 'desc')->take($limit);
        }

        $messages = $query->get();

        if (!$request->has('after_id')) {
            $messages = $messages->reverse()->values();
        }

        $messages = $messages->map(function ($msg) {
            return $this->transformMessage($msg);
        });

        return response()->json($messages);
    }

    /**
     * POST /api/mobile/{role}/chat/send
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|exists:chat_conversations,id',
            'body'            => 'nullable|string|max:5000',
            'attachments'     => 'nullable|array|max:5',
            'attachments.*'   => 'file|max:10240',
        ]);

        $conv = ChatConversation::findOrFail($request->conversation_id);
        if (!$conv->participants()->where('user_id', Auth::id())->exists()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$request->body && !$request->hasFile('attachments')) {
            return response()->json(['error' => 'Message cannot be empty'], 422);
        }

        DB::beginTransaction();
        try {
            $message = ChatMessage::create([
                'conversation_id' => $request->conversation_id,
                'user_id'         => Auth::id(),
                'body'            => $request->body,
                'type'            => $request->hasFile('attachments') ? 'file' : 'text',
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('chat_attachments', 'public');
                    ChatAttachment::create([
                        'message_id' => $message->id,
                        'file_path'  => $path,
                        'file_name'  => $file->getClientOriginalName(),
                        'file_type'  => $file->getClientMimeType(),
                        'file_size'  => $file->getSize(),
                    ]);
                }
            }

            $conv->participants()
                ->where('user_id', Auth::id())
                ->update(['last_read_at' => now()]);

            DB::commit();

            $message->load(['user', 'attachments']);
            return response()->json($this->transformMessage($message));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MobileChat sendMessage error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to send message'], 500);
        }
    }

    /**
     * POST /api/mobile/{role}/chat/create
     * Create or return existing 1-on-1 conversation.
     */
    public function createConversation(Request $request)
    {
        $request->validate([
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        $userIds = array_unique(array_merge($request->user_ids, [Auth::id()]));

        if (count($userIds) < 2) {
            return response()->json(['error' => 'At least 2 participants required'], 422);
        }

        // Patient restriction: can only create conversations with their doctors
        if ($this->isPatientUser()) {
            $allowedDoctorIds = $this->getPatientDoctorUserIds();
            $otherIds = array_diff($userIds, [Auth::id()]);
            foreach ($otherIds as $otherId) {
                if (!in_array($otherId, $allowedDoctorIds)) {
                    return response()->json(['error' => 'You can only message doctors you have visited'], 403);
                }
            }
        }

        // Check for existing 1-on-1
        if (count($userIds) == 2) {
            $otherId = collect($userIds)->first(fn($id) => $id != Auth::id());
            $existing = ChatConversation::where('is_group', false)
                ->whereHas('participants', fn($q) => $q->where('user_id', $otherId))
                ->whereHas('participants', fn($q) => $q->where('user_id', Auth::id()))
                ->first();

            if ($existing) {
                $existing->load(['participants.user', 'latestMessage.user']);
                return response()->json($this->transformConversation($existing, Auth::id()));
            }
        }

        DB::beginTransaction();
        try {
            $conv = ChatConversation::create([
                'is_group' => count($userIds) > 2,
            ]);

            foreach ($userIds as $id) {
                $conv->participants()->create(['user_id' => $id]);
            }

            DB::commit();

            $conv->load(['participants.user', 'latestMessage']);
            return response()->json($this->transformConversation($conv, Auth::id()));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MobileChat createConversation error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create conversation'], 500);
        }
    }

    /**
     * POST /api/mobile/{role}/chat/mark-read/{conversationId}
     */
    public function markAsRead($conversationId)
    {
        $conv = ChatConversation::findOrFail($conversationId);

        // Must be a participant
        if (!$conv->participants()->where('user_id', Auth::id())->exists()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Patient restriction: conversation must involve one of their doctors
        if ($this->isPatientUser()) {
            $doctorUserIds = $this->getPatientDoctorUserIds();
            $otherParticipantIds = $conv->participants()->where('user_id', '!=', Auth::id())->pluck('user_id')->toArray();
            if (empty(array_intersect($otherParticipantIds, $doctorUserIds))) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        $conv->participants()
            ->where('user_id', Auth::id())
            ->update(['last_read_at' => now()]);

        return response()->json(['status' => true]);
    }

    /**
     * GET /api/mobile/{role}/chat/unread-count
     */
    public function unreadCount()
    {
        $userId = Auth::id();

        $convIds = ChatParticipant::where('user_id', $userId)
            ->pluck('conversation_id');

        // Patient restriction
        if ($this->isPatientUser()) {
            $doctorUserIds = $this->getPatientDoctorUserIds();
            $convIds = ChatParticipant::where('user_id', $userId)
                ->whereIn('conversation_id', function ($q) use ($doctorUserIds, $userId) {
                    $q->select('conversation_id')
                      ->from('chat_participants')
                      ->where('user_id', '!=', $userId)
                      ->whereIn('user_id', $doctorUserIds);
                })
                ->pluck('conversation_id');
        }

        // Single optimized query instead of N+1
        $count = ChatMessage::whereIn('conversation_id', $convIds)
            ->where('user_id', '!=', $userId)
            ->where(function ($q) use ($userId) {
                $q->whereExists(function ($sub) use ($userId) {
                    $sub->select(DB::raw(1))
                        ->from('chat_participants')
                        ->whereColumn('chat_participants.conversation_id', 'chat_messages.conversation_id')
                        ->where('chat_participants.user_id', $userId)
                        ->where(function ($inner) {
                            $inner->whereNull('chat_participants.last_read_at')
                                  ->orWhereColumn('chat_messages.created_at', '>', 'chat_participants.last_read_at');
                        });
                });
            })
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    /**
     * DELETE /api/mobile/{role}/chat/messages/{messageId}
     */
    public function deleteMessage($messageId)
    {
        $message = ChatMessage::findOrFail($messageId);

        if ($message->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $message->update([
            'deleted_at' => now(),
            'deleted_by' => Auth::id(),
            'body'       => null,
        ]);

        return response()->json(['status' => true]);
    }

    /**
     * POST /api/mobile/{role}/chat/archive/{conversationId}
     */
    public function archiveConversation($conversationId)
    {
        $conv = ChatConversation::findOrFail($conversationId);
        if (!$conv->participants()->where('user_id', Auth::id())->exists()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Patient restriction
        if ($this->isPatientUser()) {
            $doctorUserIds = $this->getPatientDoctorUserIds();
            $otherParticipantIds = $conv->participants()->where('user_id', '!=', Auth::id())->pluck('user_id')->toArray();
            if (empty(array_intersect($otherParticipantIds, $doctorUserIds))) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        DB::table('chat_conversation_archives')->updateOrInsert(
            ['conversation_id' => $conversationId, 'user_id' => Auth::id()],
            ['archived_at' => now(), 'updated_at' => now()]
        );

        return response()->json(['status' => true]);
    }

    /**
     * POST /api/mobile/{role}/chat/unarchive/{conversationId}
     */
    public function unarchiveConversation($conversationId)
    {
        $conv = ChatConversation::findOrFail($conversationId);
        if (!$conv->participants()->where('user_id', Auth::id())->exists()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Patient restriction
        if ($this->isPatientUser()) {
            $doctorUserIds = $this->getPatientDoctorUserIds();
            $otherParticipantIds = $conv->participants()->where('user_id', '!=', Auth::id())->pluck('user_id')->toArray();
            if (empty(array_intersect($otherParticipantIds, $doctorUserIds))) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        DB::table('chat_conversation_archives')
            ->where('conversation_id', $conversationId)
            ->where('user_id', Auth::id())
            ->delete();

        return response()->json(['status' => true]);
    }

    // ─────────────────────────────────────────────────────────────
    //  DOCTOR-ONLY: Search all staff
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/mobile/doctor/chat/search-users?q=
     */
    public function searchUsers(Request $request)
    {
        if ($this->isPatientUser()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $staff = Staff::with(['user.category', 'clinic', 'specialization'])
            ->whereHas('user', function ($uq) use ($q) {
                $uq->where('id', '!=', Auth::id())
                   ->where(function ($sub) use ($q) {
                       $sub->where('firstname', 'like', "%{$q}%")
                           ->orWhere('surname', 'like', "%{$q}%")
                           ->orWhere('email', 'like', "%{$q}%");
                   });
            })
            ->limit(15)
            ->get();

        $results = $staff->map(function ($s) {
            $user = $s->user;
            $hasImage = !empty($user->filename) && file_exists(public_path('storage/image/user/' . $user->filename));
            return [
                'id'             => $user->id,
                'name'           => trim(($user->firstname ?? '') . ' ' . ($user->surname ?? '')),
                'category'       => $user->category->name ?? 'Staff',
                'specialization' => $s->specialization->name ?? '',
                'department'     => $s->clinic->name ?? '',
                'avatar_url'     => $hasImage ? url('storage/image/user/' . $user->filename) : null,
                'initials'       => strtoupper(substr($user->firstname ?? '', 0, 1) . substr($user->surname ?? '', 0, 1)),
            ];
        });

        return response()->json($results);
    }

    // ─────────────────────────────────────────────────────────────
    //  PATIENT-ONLY: My Doctors list
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/mobile/patient/chat/my-doctors
     * Returns distinct doctors the patient has had encounters with.
     */
    public function myDoctors()
    {
        $patient = $this->getAuthenticatedPatient();
        if (!$patient) {
            return response()->json(['error' => 'Patient not found'], 404);
        }

        // Load all encounter stats in a single query
        $encounterStats = Encounter::where('patient_id', $patient->id)
            ->where('completed', true)
            ->whereNotNull('doctor_id')
            ->groupBy('doctor_id')
            ->selectRaw('doctor_id, COUNT(*) as encounter_count, MAX(completed_at) as last_visit')
            ->get()
            ->keyBy('doctor_id');

        $doctorUserIds = Encounter::where('patient_id', $patient->id)
            ->whereNotNull('doctor_id')
            ->distinct()
            ->pluck('doctor_id')
            ->toArray();

        $doctors = User::with(['staff_profile.clinic', 'staff_profile.specialization', 'category'])
            ->whereIn('id', $doctorUserIds)
            ->get()
            ->map(function ($doc) use ($encounterStats) {
                $staff = $doc->staff_profile;
                $hasImage = !empty($doc->filename) && file_exists(public_path('storage/image/user/' . $doc->filename));
                $stats = $encounterStats->get($doc->id);

                return [
                    'user_id'         => $doc->id,
                    'name'            => trim(($doc->firstname ?? '') . ' ' . ($doc->surname ?? '')),
                    'category'        => $doc->category->name ?? 'Doctor',
                    'specialization'  => $staff->specialization->name ?? '',
                    'department'      => $staff->clinic->name ?? '',
                    'avatar_url'      => $hasImage ? url('storage/image/user/' . $doc->filename) : null,
                    'initials'        => strtoupper(substr($doc->firstname ?? '', 0, 1) . substr($doc->surname ?? '', 0, 1)),
                    'encounter_count' => $stats ? $stats->encounter_count : 0,
                    'last_visit'      => $stats && $stats->last_visit ? $stats->last_visit : null,
                ];
            });

        return response()->json($doctors->values());
    }

    // ─────────────────────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────────────────────

    private function isPatientUser(): bool
    {
        $user = Auth::user();
        return $user && Patient::where('user_id', $user->id)->exists();
    }

    private function getPatientDoctorUserIds(): array
    {
        $patient = $this->getAuthenticatedPatient();
        if (!$patient) {
            return [];
        }

        return Encounter::where('patient_id', $patient->id)
            ->whereNotNull('doctor_id')
            ->distinct()
            ->pluck('doctor_id')
            ->toArray();
    }

    private function getAuthenticatedPatient(): ?Patient
    {
        return Patient::where('user_id', Auth::id())->first();
    }

    private function transformConversation(ChatConversation $conv, int $userId): array
    {
        $others = $conv->participants->where('user_id', '!=', $userId);
        $colors = ['#007bff', '#6610f2', '#6f42c1', '#e83e8c', '#dc3545', '#fd7e14', '#ffc107', '#28a745', '#20c997', '#17a2b8'];

        if (!$conv->is_group) {
            $other = $others->first()?->user;
            $displayName = $other ? trim($other->firstname . ' ' . $other->surname) : 'Unknown';
            $hasImage = $other && !empty($other->filename) && file_exists(public_path('storage/image/user/' . $other->filename));
            $avatarUrl = $hasImage ? url('storage/image/user/' . $other->filename) : null;
            $initials = $other ? strtoupper(substr($other->firstname ?? '', 0, 1) . substr($other->surname ?? '', 0, 1)) : '??';
            $avatarColor = $colors[(($other->id ?? 0)) % count($colors)];
        } else {
            $displayName = $conv->title ?? 'Group Chat';
            $avatarUrl = null;
            $initials = 'GC';
            $avatarColor = $colors[0];
        }

        // Unread count
        $participant = $conv->participants->where('user_id', $userId)->first();
        $unread = 0;
        if ($participant) {
            $mq = ChatMessage::where('conversation_id', $conv->id)
                ->where('user_id', '!=', $userId);
            if ($participant->last_read_at) {
                $mq->where('created_at', '>', $participant->last_read_at);
            }
            $unread = $mq->count();
        }

        $latest = $conv->latestMessage;

        return [
            'id'           => $conv->id,
            'is_group'     => (bool) $conv->is_group,
            'display_name' => $displayName,
            'avatar_url'   => $avatarUrl,
            'initials'     => $initials,
            'avatar_color' => $avatarColor,
            'unread_count' => $unread,
            'latest_message' => $latest ? [
                'id'         => $latest->id,
                'body'       => $latest->isDeleted() ? 'This message was deleted' : $latest->body,
                'sender_name'=> $latest->user ? trim($latest->user->firstname . ' ' . $latest->user->surname) : 'Unknown',
                'is_mine'    => $latest->user_id === $userId,
                'created_at' => $latest->created_at->toIso8601String(),
                'type'       => $latest->type,
            ] : null,
            'participants' => $conv->participants->map(function ($p) use ($colors) {
                $u = $p->user;
                if (!$u) {
                    return ['id' => $p->user_id, 'name' => 'Deleted User', 'avatar_url' => null, 'initials' => '??'];
                }
                $hasImg = !empty($u->filename) && file_exists(public_path('storage/image/user/' . $u->filename));
                return [
                    'id'         => $u->id,
                    'name'       => trim(($u->firstname ?? '') . ' ' . ($u->surname ?? '')),
                    'avatar_url' => $hasImg ? url('storage/image/user/' . $u->filename) : null,
                    'initials'   => strtoupper(substr($u->firstname ?? '', 0, 1) . substr($u->surname ?? '', 0, 1)),
                ];
            })->values(),
        ];
    }

    private function transformMessage(ChatMessage $msg): array
    {
        $user = $msg->user;
        $hasImage = $user && !empty($user->filename) && file_exists(public_path('storage/image/user/' . $user->filename));

        return [
            'id'              => $msg->id,
            'conversation_id' => $msg->conversation_id,
            'user_id'         => $msg->user_id,
            'body'            => $msg->isDeleted() ? null : $msg->body,
            'type'            => $msg->type,
            'is_deleted'      => $msg->isDeleted(),
            'sender_name'     => $user ? trim(($user->firstname ?? '') . ' ' . ($user->surname ?? '')) : 'Unknown',
            'sender_avatar'   => $hasImage ? url('storage/image/user/' . $user->filename) : null,
            'sender_initials' => $user ? strtoupper(substr($user->firstname ?? '', 0, 1) . substr($user->surname ?? '', 0, 1)) : '??',
            'attachments'     => $msg->attachments->map(fn($a) => [
                'id'        => $a->id,
                'file_name' => $a->file_name,
                'file_type' => $a->file_type,
                'file_size' => $a->file_size,
                'url'       => url('storage/' . $a->file_path),
            ])->values(),
            'created_at' => $msg->created_at->toIso8601String(),
        ];
    }
}
