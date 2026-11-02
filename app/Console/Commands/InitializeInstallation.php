<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

final class InitializeInstallation extends Command
{
    protected $signature = 'app:initialize {--config= : Absolute private JSON file outside the public directory}';

    protected $description = 'One-time CLI-only installation for a DirectAdmin cron job';

    public function handle(): int
    {
        $path = realpath((string) $this->option('config'));
        $public = realpath(public_path());
        if (! $path || ! is_file($path) || is_link((string) $this->option('config')) || str_starts_with($path, $public.DIRECTORY_SEPARATOR) || ($path === $public) || (fileperms($path) & 0077) !== 0) {
            $this->error('Use a private regular JSON file with permissions 0600 outside the web root.');

            return 1;
        }
        $lock = fopen(storage_path('framework/installation.lock'), 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->error('Installation is already running.');

            return 1;
        }
        try {
            $d = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $v = Validator::make($d, ['email' => 'required|email|max:200', 'name' => 'required|string|max:100', 'organization' => 'required|string|max:100', 'password' => 'required|string|min:12', 'expires_at' => 'required|date']);
            if ($v->fails()) {
                $this->error('Invalid installation configuration. Check the required fields and password length.');

                return 1;
            }
            $expires = strtotime($d['expires_at']);
            if (! $expires || $expires < time() || $expires > time() + 86400) {
                $this->error('Installation configuration must expire within the next 24 hours.');

                return 1;
            }
            if (Schema::hasTable('users') && DB::table('users')->exists()) {
                $this->error('Initial installation is disabled: a user already exists.');

                return 1;
            }
            if (! config('app.key') && $this->call('key:generate', ['--force' => true]) !== 0) {
                return 1;
            }
            if ($this->call('migrate', ['--force' => true]) !== 0) {
                return 1;
            }
            $id = DB::transaction(function () use ($d) {
                $u = User::create(['name' => $d['name'], 'email' => $d['email'], 'password' => $d['password']]);
                $org = DB::table('organizations')->insertGetId(['name' => $d['organization'], 'created_at' => now(), 'updated_at' => now()]);
                DB::table('organization_members')->insert(['organization_id' => $org, 'user_id' => $u->id, 'role' => 'manager']);

                return $u->id;
            });
            file_put_contents(storage_path('app/private/installation-audit.log'), now()->toIso8601String().' Initial manager created, user='.$id.PHP_EOL, FILE_APPEND | LOCK_EX);
            if (! unlink($path)) {
                $this->error('Account created, but remove the private configuration file manually.');

                return 1;
            }
            $this->info('Installation completed; private bootstrap configuration removed. Remove the one-time cron job.');

            return 0;
        } catch (\Throwable $e) {
            report($e);
            $this->error('Installation failed. Inspect the private application log; no credentials are printed here.');

            return 1;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
