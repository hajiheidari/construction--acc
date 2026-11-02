<?php

namespace Database\Seeders;

use App\Accounting\AccountingService;
use App\Accounting\ProjectService;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Synthetic data is forbidden in production.');
        }
        $password = getenv('DEMO_PASSWORD');
        if (! $password || strlen($password) < 12) {
            throw new \RuntimeException('Set DEMO_PASSWORD (12+ characters) for the synthetic dataset.');
        }
        if (User::where('email', 'demo@example.test')->exists()) {
            return;
        }
        DB::transaction(function () use ($password) {
            $u = User::create(['name' => 'مدیر آزمایشی', 'email' => 'demo@example.test', 'password' => $password]);
            $org = DB::table('organizations')->insertGetId(['name' => 'سازمان نمایشی (داده ساختگی)', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('organization_members')->insert(['organization_id' => $org, 'user_id' => $u->id, 'role' => 'manager']);
            $date = now()->toDateString();
            $p = app(ProjectService::class)->create($u, ['name' => 'ساختمان نمونه — داده آزمایشی', 'code' => 'DEMO', 'commencement_date' => $date, 'history_mode' => 'opening', 'budget' => '5000000000', 'address' => 'نشانی ساختگی برای توسعه']);
            $o = DB::table('parties')->insertGetId(['organization_id' => $org, 'name' => 'مالک و تأمین‌کننده نمونه', 'identity_code' => 'DEMO-OWNER']);
            $o2 = DB::table('parties')->insertGetId(['organization_id' => $org, 'name' => 'مالک دوم نمونه', 'identity_code' => 'DEMO-OWNER2']);
            $vendor = DB::table('parties')->insertGetId(['organization_id' => $org, 'name' => 'فروشنده مصالح نمونه', 'identity_code' => 'DEMO-SUPPLIER']);
            $bank = DB::table('bank_accounts')->insertGetId(['project_id' => $p->id, 'name' => 'حساب آزمایشی پروژه']);
            $contract = DB::table('contracts')->insertGetId(['project_id' => $p->id, 'number' => 'DEMO-001', 'kind' => 'ownership', 'date' => $date, 'description' => 'قرارداد ساختگی توسعه']);
            app(ProjectService::class)->rule($u, $p->id, ['contract_id' => $contract, 'effective_date' => $date, 'posting_date' => $date, 'fee_bps' => 1000, 'shares' => [$o => 6000, $o2 => 4000], 'mode' => 'prospective'], true);
            $s = app(AccountingService::class);
            $post = function ($kind, $data, $key) use ($s, $u, $p, $date) {
                $doc = $s->draft($u, $p->id, ['kind' => $kind, 'date' => $date, 'description' => 'داده آزمایشی: '.AccountingService::KINDS[$kind], 'idempotency_key' => 'demo:'.$key, 'data' => $data]);

                return $s->approve($u, $p->id, $doc->id);
            };
            $post('opening', ['reconciled' => true, 'lines' => [['account' => 'bank', 'debit' => 300000000, 'credit' => 0, 'bank_id' => $bank], ['account' => 'contribution', 'debit' => 0, 'credit' => 300000000, 'party_id' => $o]]], 'opening');
            $i = $post('invoice', ['party_id' => $vendor, 'items' => [['description' => 'میلگرد نمونه', 'amount' => 100000000, 'fee_eligible' => true], ['description' => 'حمل نمونه', 'amount' => 10000000, 'fee_eligible' => true]]], 'invoice');
            $post('payment', ['party_id' => $vendor, 'bank_id' => $bank, 'allocations' => [['invoice_id' => $i->id, 'amount' => 50000000]]], 'payment');
            $ownInvoice = $post('invoice', ['party_id' => $o, 'items' => [['description' => 'مصالح تأمین‌شده توسط مالک نمونه', 'amount' => 20000000]]], 'owner-invoice');
            $post('offset', ['party_id' => $o, 'allocations' => [['invoice_id' => $ownInvoice->id, 'amount' => 10000000]]], 'offset');
            $post('personal_payment', ['party_id' => $vendor, 'allocations' => [['invoice_id' => $i->id, 'amount' => 5000000]]], 'personal');
            $post('outgoing_check', ['party_id' => $vendor, 'bank_id' => $bank, 'number' => 'DEMO-CHECK-1', 'bank_name' => 'بانک آزمایشی', 'due_date' => now()->addDays(3)->toDateString(), 'allocations' => [['invoice_id' => $i->id, 'amount' => 10000000]]], 'outgoing-check');
            $post('incoming_check', ['party_id' => $o2, 'bank_id' => $bank, 'amount' => 50000000, 'number' => 'DEMO-CHECK-2', 'bank_name' => 'بانک آزمایشی', 'due_date' => now()->addDays(5)->toDateString()], 'incoming-check');
        });
    }
}
