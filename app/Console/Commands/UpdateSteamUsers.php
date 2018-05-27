<?php

namespace Zeropingheroes\Lanager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Zeropingheroes\Lanager\Services\UpdateSteamUsersService;
use Zeropingheroes\Lanager\SteamUserMetadata;
use Zeropingheroes\Lanager\User;
use Zeropingheroes\Lanager\UserOAuthAccount;

class UpdateSteamUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lanager:update-steam-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update existing LANager users\' profiles with the latest information from their Steam profile';

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {
        // If there's a current LAN set
        if (Cache::get('currentLan')) {

            // Get the attendees for the current LAN
            $attendees = Cache::get('currentLan')->users()->get()->pluck('id');

            // Also get any users who have not been updated in the last day
            $staleUsers = SteamUserMetadata::whereNotIn('user_id', $attendees)
                ->where('profile_updated_at', '<=', now()->subDay())
                ->get()
                ->pluck('user_id');

            $users = $attendees->merge($staleUsers);
        } else {
            // Or if there isn't a current LAN set, get all users
            $users = User::all()->pluck('id');
        }

        // Get the Steam IDs belonging to the users who are to be updated
        $steamIds = UserOAuthAccount::whereIn('user_id', $users)
            ->get()
            ->pluck('provider_id')
            ->toArray();

        if (!$steamIds) {
            $message = __('phrase.no-steam-users-to-update');
            Log::info($message);
            $this->info($message);
            return;
        }

        $this->info(__('phrase.updating-profiles-and-online-status-for-x-users-from-steam', ['x' => count($steamIds)]));

        $service = new UpdateSteamUsersService($steamIds);
        $service->update();

        $message = __(
            'phrase.successfully-updated-profiles-and-online-status-for-x-of-y-users',
            ['x' => count($service->getUpdated()), 'y' => count($steamIds)]
        );
        Log::info($message, $service->getUpdated());
        $this->info($message);

        if ($service->errors()->isNotEmpty()) {
            $this->error(__('phrase.the-following-errors-were-encountered'));
            foreach ($service->errors()->getMessages() as $error) {
                Log::error($error[0]);
                $this->error($error[0]);
            }
        }
    }
}
