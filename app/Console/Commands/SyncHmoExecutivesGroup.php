<?php

namespace App\Console\Commands;

use App\Models\ChatConversation;
use App\Models\ChatParticipant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncHmoExecutivesGroup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hmo:sync-executives-group {--force : Force sync even if cache is still valid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync users with HMO Executive role to the HMO Executives messenger group';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Check cache to avoid running too frequently (unless forced)
        $cacheKey = 'hmo_executives_group_sync';
        if (!$this->option('force') && Cache::has($cacheKey)) {
            $this->info('Sync was run recently. Use --force to sync anyway.');
            return 0;
        }

        $this->info('Starting HMO Executives group sync...');

        DB::beginTransaction();
        try {
            // Find or create the HMO Executives group
            $conversation = ChatConversation::firstOrCreate(
                ['title' => 'HMO Executives'],
                [
                    'is_group' => true,
                ]
            );

            $this->info("Group ID: {$conversation->id}");

            // Get all users with HMO Executive role
            $hmoExecutives = User::whereHas('roles', function ($query) {
                $query->where('name', 'HMO Executive');
            })->where('status', 1)->get();

            $this->info("Found {$hmoExecutives->count()} HMO Executives");

            // Also include SUPERADMINs and ADMINs for oversight
            $admins = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['SUPERADMIN', 'ADMIN']);
            })->where('status', 1)->get();

            $this->info("Found {$admins->count()} Admins for oversight");

            // Merge all users (unique by ID)
            $allUsers = $hmoExecutives->merge($admins)->unique('id');
            $userIds = $allUsers->pluck('id')->toArray();

            // Get current participants
            $currentParticipants = ChatParticipant::where('conversation_id', $conversation->id)
                ->pluck('user_id')
                ->toArray();

            // Add new users
            $newUsers = array_diff($userIds, $currentParticipants);
            $removedUsers = array_diff($currentParticipants, $userIds);

            foreach ($newUsers as $userId) {
                ChatParticipant::firstOrCreate([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                ]);
                $this->info("Added user ID: {$userId}");
            }

            // Remove users who no longer have the role (optional - uncomment if needed)
            // foreach ($removedUsers as $userId) {
            //     ChatParticipant::where('conversation_id', $conversation->id)
            //         ->where('user_id', $userId)
            //         ->delete();
            //     $this->info("Removed user ID: {$userId}");
            // }

            DB::commit();

            // Cache the sync time for 1 hour
            Cache::put($cacheKey, now(), 3600);

            $this->info('HMO Executives group sync completed successfully!');
            $this->info("Total members: " . count($userIds));
            $this->info("New members added: " . count($newUsers));

            Log::info('HMO Executives group synced', [
                'total_members' => count($userIds),
                'new_members' => count($newUsers),
            ]);

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error syncing HMO Executives group: ' . $e->getMessage());
            Log::error('HMO Executives group sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }
}
