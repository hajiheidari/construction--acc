<?php

namespace App\Accounting;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class ProjectService
{
    public function create(User $u, array $d): object
    {
        Validator::make($d, ['name' => 'required|string|max:150', 'code' => 'required|string|max:40', 'commencement_date' => 'required|date_format:Y-m-d', 'history_mode' => 'required|in:opening,detailed', 'budget' => 'required', 'address' => 'nullable|string|max:1000'])->validate();
        $org = DB::table('organization_members')->where('user_id', $u->id)->where('role', 'manager')->first();
        abort_unless($org, 403);

        return DB::transaction(function () use ($u, $d, $org) {
            if (DB::table('projects')->where('organization_id', $org->organization_id)->where('code', $d['code'])->exists()) {
                Money::fail('کد پروژه تکراری است.');
            }
            $id = DB::table('projects')->insertGetId(['organization_id' => $org->organization_id, 'name' => $d['name'], 'code' => $d['code'], 'commencement_date' => $d['commencement_date'], 'history_mode' => $d['history_mode'], 'budget' => Money::amount($d['budget'], true), 'address' => $d['address'] ?? '', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('project_members')->insert(['project_id' => $id, 'user_id' => $u->id, 'role' => 'manager']);
            $year = substr($d['commencement_date'], 0, 4);
            DB::table('fiscal_years')->insert(['project_id' => $id, 'name' => $year, 'starts_on' => $year.'-01-01', 'ends_on' => $year.'-12-31']);
            app(AccountingService::class)->audit($u, $id, 'project.created', null);

            return DB::table('projects')->find($id);
        });
    }

    public function rule(User $u, int $p, array $d, bool $save = false): array
    {
        Validator::make($d, ['contract_id' => 'required|integer', 'effective_date' => 'required|date_format:Y-m-d', 'fee_bps' => 'required|integer|min:0|max:10000', 'shares' => 'required|array|min:1', 'shares.*' => 'required|integer|min:1|max:10000', 'mode' => 'required|in:prospective,retroactive', 'posting_date' => 'required|date_format:Y-m-d'])->validate();
        $s = app(AccountingService::class);

        return $s->locked($u, $p, function ($project) use ($u, $p, $d, $save, $s) {
            if (! DB::table('contracts')->where('project_id', $p)->where('id', $d['contract_id'])->exists()) {
                Money::fail('قرارداد پروژه نامعتبر است.');
            }
            $shares = array_map('intval', $d['shares']);
            Money::allocate(1, $shares);
            foreach ($shares as $party => $bps) {
                $s->party($p, $party);
            }
            $date = $d['mode'] === 'retroactive' ? $project->commencement_date : $d['effective_date'];
            if ($date < $project->commencement_date && $project->history_mode !== 'detailed') {
                Money::fail('تاریخ مؤثر پیش از شروع پروژه است.');
            }
            $fy = DB::table('fiscal_years')->where('project_id', $p)->where('starts_on', '<=', $d['posting_date'])->where('ends_on', '>=', $d['posting_date'])->first();
            if (! $fy || $fy->closed) {
                Money::fail('تاریخ ثبت تعدیل باید در سال مالی باز باشد.');
            }
            $changes = [];
            foreach (DB::table('documents')->where('project_id', $p)->whereIn('kind', ['invoice', 'opening'])->where('status', 'posted')->where('date', '>=', $date)->get() as $invoice) {
                $items = DB::table('invoice_items')->where('document_id', $invoice->id)->get();
                $base = (int) $items->sum('amount');
                $fee = Money::fee((int) $items->where('fee_eligible', true)->sum('amount'), (int) $d['fee_bps']);
                $entryIds = DB::table('journal_entries')->where('document_id', $invoice->id)->pluck('id');
                $oldFee = (int) DB::table('journal_lines')->whereIn('journal_entry_id', $entryIds)->where('account', 'fee_cost')->selectRaw('COALESCE(SUM(debit-credit),0) as n')->value('n');
                if ($invoice->kind === 'opening') {
                    $base = (int) DB::table('journal_lines')->whereIn('journal_entry_id', $entryIds)->where('account', 'cost')->selectRaw('COALESCE(SUM(debit-credit),0) as n')->value('n');
                    $fee = $oldFee;
                    if ($base + $fee > 0 && (int) $s->rule($p, $invoice->date)->fee_bps !== (int) $d['fee_bps']) {
                        Money::fail('مبنای هزینه مشمول حق‌الزحمه افتتاحیه جزئی نیست؛ تعدیل درصد مجری روی هزینه تجمیعی پشتیبانی نمی‌شود.');
                    }
                    if ($base + $fee === 0) {
                        continue;
                    }
                }
                $desired = Money::allocate(Money::amount($base + $fee), $shares);
                $old = DB::table('owner_cost_allocations')->where('document_id', $invoice->id)->selectRaw('party_id,SUM(amount) as amount')->groupBy('party_id')->pluck('amount', 'party_id')->map(fn ($x) => (int) $x)->all();
                $delta = [];
                foreach (array_unique([...array_keys($desired), ...array_keys($old)]) as $owner) {
                    $delta[$owner] = ($desired[$owner] ?? 0) - ($old[$owner] ?? 0);
                }
                $changes[] = ['invoice_id' => $invoice->id, 'fee_difference' => $fee - $oldFee, 'owner_differences' => $delta];
            }
            if (! $save) {
                return ['effective_date' => $date, 'changes' => $changes];
            }
            $same = DB::table('rule_versions')->where('project_id', $p)->orderByDesc('id')->first();
            if ($same && (int) $same->contract_id === (int) $d['contract_id'] && $same->effective_date === $date && (int) $same->fee_bps === (int) $d['fee_bps'] && json_decode($same->shares, true) == $shares && $same->mode === $d['mode']) {
                return ['version' => $same->id, 'effective_date' => $date, 'changes' => []];
            }
            $version = DB::table('rule_versions')->insertGetId(['project_id' => $p, 'contract_id' => $d['contract_id'], 'effective_date' => $date, 'fee_bps' => $d['fee_bps'], 'shares' => json_encode($shares), 'mode' => $d['mode'], 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($changes as $change) {
                $invoice = DB::table('documents')->find($change['invoice_id']);
                $diff = $change['fee_difference'];
                $j = DB::table('journal_entries')->where('document_id', $invoice->id)->orderBy('id')->value('id');
                if ($diff) {
                    if ($diff < 0 && -$s->balance($p, 'fee_payable') < -$diff) {
                        Money::fail('حق‌الزحمه قبلاً پرداخت شده؛ ابتدا تسویه را اصلاح کنید.');
                    }
                    $j = $s->journal($invoice, [['account' => 'fee_cost', 'debit' => max(0, $diff), 'credit' => max(0, -$diff), 'party_id' => null, 'bank_account_id' => null, 'invoice_id' => null], ['account' => 'fee_payable', 'debit' => max(0, -$diff), 'credit' => max(0, $diff), 'party_id' => null, 'bank_account_id' => null, 'invoice_id' => null]], 'amendment:'.$version.':'.$invoice->id, $d['posting_date'], 'amendment');
                }
                foreach ($change['owner_differences'] as $owner => $amount) {
                    if ($amount) {
                        DB::table('owner_cost_allocations')->insert(['project_id' => $p, 'document_id' => $invoice->id, 'rule_version_id' => $version, 'party_id' => $owner, 'journal_entry_id' => $j, 'date' => $d['posting_date'], 'amount' => $amount]);
                    }
                }
            }
            $s->audit($u, $p, 'rule.approved', null, ['version' => $version, 'changes' => $changes]);

            return ['version' => $version, 'effective_date' => $date, 'changes' => $changes];
        }, true);
    }
}
