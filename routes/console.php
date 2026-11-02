<?php

use App\Accounting\Reports;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

Artisan::command('app:manager {email} {--name=} {--organization=}', function () {
    if (DB::table('users')->exists()) {
        $this->error('Initial manager already exists. Use project user management.');

        return 1;
    }
    $email = $this->argument('email');
    $name = $this->option('name') ?: $this->ask('Manager name');
    $organization = $this->option('organization') ?: $this->ask('Organization name');
    $password = $this->secret('Password (12+ characters)');
    $v = Validator::make(compact('email', 'name', 'organization', 'password'), ['email' => 'required|email', 'name' => 'required|string|max:100', 'organization' => 'required|string|max:100', 'password' => 'required|min:12']);
    if ($v->fails()) {
        $this->error($v->errors()->first());

        return 1;
    }
    DB::transaction(function () use ($email, $name, $organization, $password) {
        $u = User::create(compact('email', 'name', 'password'));
        $id = DB::table('organizations')->insertGetId(['name' => $organization, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('organization_members')->insert(['organization_id' => $id, 'user_id' => $u->id, 'role' => 'manager']);
    });
    $this->info('Manager created.');

    return 0;
});
Artisan::command('app:reconcile', function () {
    $failed = false;
    foreach (DB::table('projects')->get() as $p) {
        $r = app(Reports::class)->summary($p->id)['reconciliation'];
        $this->line($p->code.': '.json_encode($r));
        if ($r['unbalanced_journals'] || $r['trial_difference'] || $r['allocation_difference']) {
            $failed = true;
        }
    }

    return $failed ? 1 : 0;
});
