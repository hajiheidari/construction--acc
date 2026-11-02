<?php

use App\Accounting\AccountingService;
use App\Accounting\ProjectService;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

// Run only on an isolated local/testing MySQL database; creates synthetic fixtures.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment(['local', 'testing']) || DB::getDriverName() !== 'mysql') {
    throw new RuntimeException('Use an isolated local/testing MySQL database.');
}
if (($argv[1] ?? '') === 'worker') {
    app(AccountingService::class)->approve(User::findOrFail($argv[2]), (int) $argv[3], (int) $argv[4]);
    exit(0);
}
$u = User::factory()->create();
$org = DB::table('organizations')->insertGetId(['name' => 'Concurrency synthetic']);
DB::table('organization_members')->insert(['organization_id' => $org, 'user_id' => $u->id, 'role' => 'manager']);
$date = now()->toDateString();
$p = app(ProjectService::class)->create($u, ['name' => 'Concurrency synthetic', 'code' => 'RACE-'.bin2hex(random_bytes(4)), 'commencement_date' => $date, 'history_mode' => 'detailed', 'budget' => 0]);
$party = DB::table('parties')->insertGetId(['organization_id' => $org, 'name' => 'Synthetic owner supplier']);
$c = DB::table('contracts')->insertGetId(['project_id' => $p->id, 'number' => 'RACE', 'kind' => 'ownership', 'date' => $date, 'description' => 'Synthetic']);
app(ProjectService::class)->rule($u, $p->id, ['contract_id' => $c, 'effective_date' => $date, 'posting_date' => $date, 'fee_bps' => 1000, 'shares' => [$party => 10000], 'mode' => 'prospective'], true);
$d = app(AccountingService::class)->draft($u, $p->id, ['kind' => 'invoice', 'date' => $date, 'description' => 'Concurrency fixture', 'idempotency_key' => 'race', 'data' => ['party_id' => $party, 'items' => [['description' => 'Synthetic cost', 'amount' => 1000000]]]]);
$workers = [];
for ($i = 0; $i < 2; $i++) {
    $process = new Process([PHP_BINARY, __FILE__, 'worker', (string) $u->id, (string) $p->id, (string) $d->id], dirname(__DIR__));
    $process->start();
    $workers[] = $process;
}
foreach ($workers as $process) {
    $process->wait();
    if (! $process->isSuccessful()) {
        throw new RuntimeException($process->getErrorOutput().$process->getOutput());
    }
}
$count = DB::table('journal_entries')->where('document_id', $d->id)->count();
$cost = app(AccountingService::class)->balance($p->id, 'cost');
if ($count !== 1 || $cost !== 1000000) {
    throw new RuntimeException('Concurrent posting duplicated journal or cost.');
}echo "PASS: two independent approval processes; one journal; exact 1,000,000 rial cost.\n";
