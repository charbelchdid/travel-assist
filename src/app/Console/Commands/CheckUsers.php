<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class CheckUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:check {username?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check users in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $username = $this->argument('username');

        if ($username) {
            $user = User::where('username', $username)->first();

            if ($user) {
                $this->info("User found:");
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['ID', $user->id],
                        ['External ID', $user->external_id],
                        ['Username', $user->username],
                        ['Email', $user->email],
                        ['Name', $user->name],
                        ['First Name', $user->first_name],
                        ['Last Name', $user->last_name],
                        ['Phone', $user->phone],
                        ['Is Admin', $user->is_admin ? 'Yes' : 'No'],
                        ['Role', $user->role],
                        ['Department', $user->department],
                        ['Branch ID', $user->branch_id],
                        ['Branch Name', $user->branch_name],
                        ['Device ID', $user->device_id],
                        ['Last Login', $user->last_login_at],
                        ['Created At', $user->created_at],
                        ['Updated At', $user->updated_at],
                    ]
                );

                if ($user->external_data) {
                    $this->info("\nExternal Data:");
                    $this->line(json_encode($user->external_data, JSON_PRETTY_PRINT));
                }
            } else {
                $this->error("User '$username' not found");
            }
        } else {
            $users = User::all();
            $this->info("Total users in database: " . $users->count());

            if ($users->count() > 0) {
                $tableData = $users->map(function ($user) {
                    return [
                        $user->id,
                        $user->external_id,
                        $user->username,
                        $user->name,
                        $user->email,
                        $user->is_admin ? 'Yes' : 'No',
                        $user->role,
                        $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : 'Never',
                    ];
                })->toArray();

                $this->table(
                    ['ID', 'Ext ID', 'Username', 'Name', 'Email', 'Admin', 'Role', 'Last Login'],
                    $tableData
                );
            }
        }

        return 0;
    }
}
