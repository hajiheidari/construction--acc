<?php

namespace App\Accounting;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class AccountingService
{
    public const ACCOUNTS = ['bank' => 'بانک', 'cost' => 'هزینه پروژه', 'fee_cost' => 'هزینه مدیریت', 'supplier' => 'بستانکار تأمین‌کننده', 'fee_payable' => 'حق‌الزحمه پرداختنی', 'reimbursement' => 'مطالبات شخصی مجری', 'contribution' => 'آورده پذیرفته‌شده مالک', 'incoming_check' => 'چک در جریان وصول', 'pending_contribution' => 'آورده وصول‌نشده', 'outgoing_check' => 'چک پرداختنی', 'opening_control' => 'کنترل افتتاحیه'];

    public const KINDS = ['invoice' => 'فاکتور هزینه', 'contribution' => 'دریافت آورده', 'payment' => 'پرداخت تأمین‌کننده', 'personal_payment' => 'پرداخت از وجه شخصی مجری', 'direct_payment' => 'پرداخت مستقیم مالک', 'offset' => 'تهاتر آورده مالک', 'settlement' => 'تسویه مجری', 'transfer' => 'انتقال بانکی', 'incoming_check' => 'چک دریافتی', 'outgoing_check' => 'چک پرداختی', 'opening' => 'مانده افتتاحیه'];

    public function locked(User $user, int $project, callable $fn, bool $manager = false): mixed
    {
        Access::project($user, $project, true, $manager);

        return DB::transaction(function () use ($project, $fn) {
            $p = DB::table('projects')->where('id', $project)->lockForUpdate()->first();

            return $fn($p);
        }, 3);
    }

    public function party(int $project, mixed $id): int
    {
        $p = DB::table('projects')->find($project);
        if (! DB::table('parties')->where('organization_id', $p->organization_id)->where('id', $id)->exists()) {
            Money::fail('شخص متعلق به سازمان پروژه نیست.');
        }

        return (int) $id;
    }

    public function bank(int $project, mixed $id): int
    {
        if (! DB::table('bank_accounts')->where('project_id', $project)->where('id', $id)->exists()) {
            Money::fail('حساب بانکی پروژه نامعتبر است.');
        }

        return (int) $id;
    }

    public function rule(int $project, string $date): object
    {
        $r = DB::table('rule_versions')->where('project_id', $project)->where('effective_date', '<=', $date)->orderByDesc('id')->first();
        if (! $r) {
            Money::fail('قرارداد سهم مالکان و حق‌الزحمه برای این تاریخ تعریف نشده است.');
        }

        return $r;
    }

    public function owner(int $project, mixed $id, string $date): int
    {
        $id = $this->party($project, $id);
        $r = $this->rule($project, $date);
        if (! array_key_exists($id, json_decode($r->shares, true))) {
            Money::fail('شخص در این تاریخ مالک پروژه نیست.');
        }

        return $id;
    }

    public function audit(User $u, int $p, string $event, ?int $doc, array $details = []): void
    {
        DB::table('audit_logs')->insert(['project_id' => $p, 'user_id' => $u->id, 'event' => $event, 'document_id' => $doc, 'details' => json_encode($details), 'created_at' => now()]);
    }

    public function draft(User $u, int $p, array $input): object
    {
        Access::project($u, $p, true);
        Validator::make($input, ['kind' => 'required|in:'.implode(',', array_keys(self::KINDS)), 'date' => 'required|date_format:Y-m-d', 'description' => 'required|string|max:255', 'reference' => 'nullable|string|max:100', 'idempotency_key' => 'required|string|max:100', 'data' => 'required|array'])->validate();

        return $this->locked($u, $p, function ($project) use ($u, $p, $input) {
            $old = DB::table('documents')->where('project_id', $p)->where('idempotency_key', $input['idempotency_key'])->first();
            if ($old) {
                if ($old->kind !== $input['kind'] || $old->date !== $input['date'] || json_decode($old->data, true) != $input['data']) {
                    Money::fail('کلید تکراری با اطلاعات متفاوت استفاده شده است.');
                }

                return $old;
            }
            if ($input['kind'] !== 'opening' && $input['date'] < $project->commencement_date && $project->history_mode !== 'detailed') {
                Money::fail('پروژه از روش افتتاحیه استفاده می‌کند؛ ثبت جزئی قبل از شروع مجاز نیست.');
            }
            $id = DB::table('documents')->insertGetId(['project_id' => $p, 'kind' => $input['kind'], 'date' => $input['date'], 'description' => $input['description'], 'reference' => $input['reference'] ?? null, 'idempotency_key' => $input['idempotency_key'], 'data' => json_encode($input['data']), 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
            if ($input['kind'] === 'invoice') {
                $items = $input['data']['items'] ?? [];
                if (count($items) < 1 || count($items) > 100) {
                    Money::fail('۱ تا ۱۰۰ ردیف فاکتور لازم است.');
                }
                foreach ($items as $item) {
                    Validator::make($item, ['description' => 'required|string|max:255', 'amount' => 'nullable', 'quantity' => 'nullable|string|max:30', 'unit_price' => 'nullable', 'unit' => 'nullable|string|max:30', 'fee_eligible' => 'boolean'])->validate();
                    $category = $item['category_id'] ?? null;
                    if ($category && ! DB::table('expense_categories')->where('project_id', $p)->where('id', $category)->exists()) {
                        Money::fail('سرفصل نامعتبر است.');
                    }
                    $phase = $item['phase_id'] ?? null;
                    if ($phase && ! DB::table('construction_phases')->where('project_id', $p)->where('id', $phase)->exists()) {
                        Money::fail('مرحله ساخت نامعتبر است.');
                    }
                    DB::table('invoice_items')->insert(['project_id' => $p, 'document_id' => $id, 'description' => $item['description'], 'category_id' => $category, 'amount' => Money::item($item), 'quantity' => $item['quantity'] ?? null, 'unit' => $item['unit'] ?? null, 'unit_price' => isset($item['unit_price']) && $item['unit_price'] !== '' ? Money::amount($item['unit_price']) : null, 'discount' => Money::amount($item['discount'] ?? 0, true), 'phase_id' => $phase, 'fee_eligible' => $item['fee_eligible'] ?? true]);
                }
            }
            $this->audit($u, $p, 'draft.created', $id);
            // Validate the entire preview now; approval repeats validation under the project lock.
            $doc = DB::table('documents')->find($id);
            $this->plan($doc);

            return $doc;
        });
    }

    public function balance(int $p, string $account, ?int $party = null, ?int $bank = null, ?int $invoice = null): int
    {
        $q = DB::table('journal_lines')->where('project_id', $p)->where('account', $account);
        if ($party !== null) {
            $q->where('party_id', $party);
        }if ($bank !== null) {
            $q->where('bank_account_id', $bank);
        }if ($invoice !== null) {
            $q->where('invoice_id', $invoice);
        }

        return (int) $q->selectRaw('COALESCE(SUM(debit-credit),0) as balance')->value('balance');
    }

    private function line(string $account, int $amount, ?int $party = null, ?int $bank = null, ?int $invoice = null): array
    {
        return ['account' => $account, 'debit' => max(0, $amount), 'credit' => max(0, -$amount), 'party_id' => $party, 'bank_account_id' => $bank, 'invoice_id' => $invoice];
    }

    public function plan(object $doc): array
    {
        $p = $doc->project_id;
        $d = json_decode($doc->data, true);
        $lines = [];
        $alloc = [];
        $rule = null;
        $fee = 0;
        $amount = fn () => Money::amount($d['amount'] ?? null);
        $bank = fn () => $this->bank($p, $d['bank_id'] ?? null);
        $party = fn () => $this->party($p, $d['party_id'] ?? null);
        $owner = fn () => $this->owner($p, $d['party_id'] ?? null, $doc->date);
        switch ($doc->kind) {
            case 'invoice':
                $supplier = $party();
                $items = DB::table('invoice_items')->where('document_id', $doc->id)->get();
                $base = Money::amount($items->sum('amount'));
                $eligible = (int) $items->where('fee_eligible', true)->sum('amount');
                $rule = $this->rule($p, $doc->date);
                $fee = Money::fee($eligible, $rule->fee_bps);
                $total = Money::amount($base + $fee);
                $lines = [$this->line('cost', $base), $this->line('supplier', -$base, $supplier, null, $doc->id)];
                if ($fee) {
                    $lines[] = $this->line('fee_cost', $fee);
                    $lines[] = $this->line('fee_payable', -$fee);
                }
                $alloc = Money::allocate($total, json_decode($rule->shares, true));
                break;
            case 'contribution':$a = $amount();
                $lines = [$this->line('bank', $a, null, $bank()), $this->line('contribution', -$a, $owner())];
                break;
            case 'payment': case 'personal_payment': case 'direct_payment': case 'offset': case 'outgoing_check':
                $supplier = $party();
                $settlements = $d['allocations'] ?? [];
                if (! $settlements || count($settlements) > 100) {
                    Money::fail('تخصیص به فاکتورهای تأییدشده لازم است.');
                }
                $total = 0;
                $seen = [];
                foreach ($settlements as $allocation) {
                    $invoice = (int) ($allocation['invoice_id'] ?? 0);
                    $a = Money::amount($allocation['amount'] ?? null);
                    if (isset($seen[$invoice])) {
                        Money::fail('فاکتور تکراری در تخصیص.');
                    }$seen[$invoice] = true;
                    $inv = DB::table('documents')->where('project_id', $p)->whereIn('kind', ['invoice', 'opening'])->where('status', 'posted')->find($invoice);
                    if (! $inv || ($inv->kind === 'invoice' && (int) (json_decode($inv->data, true)['party_id'] ?? 0) !== $supplier)) {
                        Money::fail('فاکتور متعلق به تأمین‌کننده نیست یا تأیید نشده است.');
                    }
                    if ($a > -$this->balance($p, 'supplier', $supplier, null, $invoice)) {
                        Money::fail('تخصیص بیشتر از مانده پرداختنی فاکتور است.');
                    }
                    $total += $a;
                    $lines[] = $this->line('supplier', $a, $supplier, null, $invoice);
                }
                Money::amount($total);
                if ($doc->kind === 'payment') {
                    $lines[] = $this->line('bank', -$total, null, $bank());
                }
                if ($doc->kind === 'personal_payment') {
                    $lines[] = $this->line('reimbursement', -$total);
                }
                if ($doc->kind === 'offset') {
                    $owner = $this->owner($p, $supplier, $doc->date);
                    $lines[] = $this->line('contribution', -$total, $owner);
                }
                if ($doc->kind === 'direct_payment') {
                    $owner = $this->owner($p, $d['owner_id'] ?? null, $doc->date);
                    $lines[] = $this->line('contribution', -$total, $owner);
                }
                if ($doc->kind === 'outgoing_check') {
                    $this->checkData($d);
                    $bank();
                    $lines[] = $this->line('outgoing_check', -$total, $supplier);
                }
                break;
            case 'settlement':
                $f = Money::amount($d['fee_amount'] ?? 0, true);
                $r = Money::amount($d['reimbursement_amount'] ?? 0, true);
                Money::amount($f + $r);
                if ($f > -$this->balance($p, 'fee_payable') || $r > -$this->balance($p, 'reimbursement')) {
                    Money::fail('تسویه بیشتر از مطالبه مجری است.');
                }
                if ($f) {
                    $lines[] = $this->line('fee_payable', $f);
                }if ($r) {
                    $lines[] = $this->line('reimbursement', $r);
                }$lines[] = $this->line('bank', -($f + $r), null, $bank());
                break;
            case 'transfer':$a = $amount();
                $from = $bank();
                $to = $this->bank($p, $d['to_bank_id'] ?? null);
                if ($from === $to) {
                    Money::fail('دو حساب انتقال باید متفاوت باشند.');
                }$lines = [$this->line('bank', -$a, null, $from), $this->line('bank', $a, null, $to)];
                break;
            case 'incoming_check':$this->checkData($d);
                $a = $amount();
                $owner = $owner();
                $bank();
                $lines = [$this->line('incoming_check', $a, $owner), $this->line('pending_contribution', -$a, $owner)];
                break;
            case 'opening':
                $project = DB::table('projects')->find($p);
                if ($project->history_mode !== 'opening' || $doc->date !== $project->commencement_date) {
                    Money::fail('افتتاحیه فقط در تاریخ شروع و روش افتتاحیه مجاز است.');
                }
                if (DB::table('documents')->where('project_id', $p)->where('kind', 'opening')->where('status', 'posted')->where('id', '!=', $doc->id)->exists()) {
                    Money::fail('افتتاحیه قبلاً ثبت شده است.');
                }
                if (empty($d['reconciled'])) {
                    Money::fail('تأیید چک‌لیست تطبیق افتتاحیه الزامی است.');
                }
                $rows = $d['lines'] ?? [];
                if (! $rows || count($rows) > 500) {
                    Money::fail('ردیف‌های افتتاحیه نامعتبرند.');
                }
                foreach ($rows as $row) {
                    $account = $row['account'] ?? '';
                    if (! array_key_exists($account, self::ACCOUNTS)) {
                        Money::fail('حساب افتتاحیه نامعتبر است.');
                    }
                    if (in_array($account, ['incoming_check', 'outgoing_check', 'pending_contribution'])) {
                        Money::fail('چک معوق را در بخش چک‌های افتتاحیه با مشخصات کامل وارد کنید.');
                    }
                    $debit = Money::amount($row['debit'] ?? 0, true);
                    $credit = Money::amount($row['credit'] ?? 0, true);
                    if (($debit > 0) === ($credit > 0)) {
                        Money::fail('هر ردیف فقط یک طرف بدهکار یا بستانکار دارد.');
                    }
                    $party = null;
                    $bank = null;
                    if (in_array($account, ['supplier', 'contribution'])) {
                        $party = $this->party($p, $row['party_id'] ?? null);
                    }
                    if ($account === 'contribution') {
                        $this->owner($p, $party, $doc->date);
                    }
                    if ($account === 'bank') {
                        $bank = $this->bank($p, $row['bank_id'] ?? null);
                    }
                    $lines[] = $this->line($account, $debit - $credit, $party, $bank, $account === 'supplier' ? $doc->id : null);
                }
                $totalCost = 0;
                foreach ($lines as $l) {
                    if (in_array($l['account'], ['cost', 'fee_cost'])) {
                        $totalCost += $l['debit'] - $l['credit'];
                    }
                }
                if ($totalCost < 0) {
                    Money::fail('هزینه انباشته افتتاحیه منفی نیست.');
                }
                if ($totalCost) {
                    $rule = $this->rule($p, $doc->date);
                    $alloc = Money::allocate(Money::amount($totalCost), json_decode($rule->shares, true));
                }
                $checkKeys = [];
                foreach ($d['checks'] ?? [] as $check) {
                    $this->checkData($check);
                    $a = Money::amount($check['amount'] ?? null);
                    $this->bank($p, $check['bank_id'] ?? null);
                    $party = $this->party($p, $check['party_id'] ?? null);
                    $direction = $check['direction'] ?? '';
                    if (! in_array($direction, ['incoming', 'outgoing'])) {
                        Money::fail('جهت چک افتتاحیه نامعتبر است.');
                    }
                    $key = $direction.':'.$check['bank_name'].':'.$check['number'];
                    if (isset($checkKeys[$key])) {
                        Money::fail('چک افتتاحیه تکراری است.');
                    }$checkKeys[$key] = true;
                    if ($direction === 'incoming') {
                        $this->owner($p, $party, $doc->date);
                        $lines[] = $this->line('incoming_check', $a, $party);
                        $lines[] = $this->line('pending_contribution', -$a, $party);
                    } else {
                        $lines[] = $this->line('opening_control', $a);
                        $lines[] = $this->line('outgoing_check', -$a, $party);
                    }
                }
                break;
            default:Money::fail('نوع سند پشتیبانی نمی‌شود.');
        }
        if (array_sum(array_column($lines, 'debit')) !== array_sum(array_column($lines, 'credit'))) {
            Money::fail('سند تراز نیست.');
        }
        // Bank balances may not become negative. Aggregate both sides for transfers/openings.
        $bankChanges = [];
        foreach ($lines as $line) {
            if ($line['account'] === 'bank') {
                $bankChanges[$line['bank_account_id']] = ($bankChanges[$line['bank_account_id']] ?? 0) + $line['debit'] - $line['credit'];
            }
        }
        foreach ($bankChanges as $id => $delta) {
            if ($this->balance($p, 'bank', null, (int) $id) + $delta < 0) {
                Money::fail('موجودی بانک کافی نیست.');
            }
        }
        Money::amount(array_sum(array_column($lines, 'debit')));

        return ['lines' => $lines, 'allocations' => $alloc, 'rule' => $rule, 'fee' => $fee];
    }

    private function checkData(array $d): void
    {
        Validator::make($d, ['number' => 'required|string|max:100', 'bank_name' => 'required|string|max:100', 'due_date' => 'required|date_format:Y-m-d', 'sayad' => 'nullable|digits:16'])->validate();
    }

    public function journal(object $doc, array $lines, string $key, ?string $date = null, string $kind = ''): int
    {
        $existing = DB::table('journal_entries')->where('project_id', $doc->project_id)->where('posting_key', $key)->first();
        if ($existing) {
            return $existing->id;
        }
        $date ??= $doc->date;
        $kind = $kind ?: $doc->kind;
        $fy = DB::table('fiscal_years')->where('project_id', $doc->project_id)->where('starts_on', '<=', $date)->where('ends_on', '>=', $date)->first();
        if (! $fy || $fy->closed) {
            Money::fail('سال مالی باز برای تاریخ سند وجود ندارد.');
        }
        if (! $lines || array_sum(array_column($lines, 'debit')) !== array_sum(array_column($lines, 'credit'))) {
            Money::fail('سند تراز نیست.');
        }
        foreach ($lines as $line) {
            if (! isset(self::ACCOUNTS[$line['account']])) {
                Money::fail('حساب نامعتبر است.');
            }Money::amount($line['debit'], true);
            Money::amount($line['credit'], true);
            if (($line['debit'] > 0) === ($line['credit'] > 0)) {
                Money::fail('ردیف دفتر نامعتبر است.');
            }
        }
        Money::amount(array_sum(array_column($lines, 'debit')));
        $scope = ['project_id' => $doc->project_id, 'fiscal_year_id' => $fy->id, 'kind' => $kind];
        DB::table('document_sequences')->insertOrIgnore($scope + ['next_number' => 1]);
        $seq = DB::table('document_sequences')->where($scope)->first();
        $number = $seq->next_number;
        DB::table('document_sequences')->where('id', $seq->id)->increment('next_number');
        $id = DB::table('journal_entries')->insertGetId(['project_id' => $doc->project_id, 'document_id' => $doc->id, 'fiscal_year_id' => $fy->id, 'number' => $number, 'kind' => $kind, 'date' => $date, 'description' => $doc->description, 'posting_key' => $key, 'created_at' => now()]);
        foreach ($lines as $line) {
            DB::table('journal_lines')->insert($line + ['project_id' => $doc->project_id, 'journal_entry_id' => $id]);
        }
        DB::table('journal_entries')->where('id', $id)->update(['status' => 'posted']);

        return $id;
    }

    public function approve(User $u, int $p, int $id): object
    {
        return $this->locked($u, $p, function () use ($u, $p, $id) {
            $doc = DB::table('documents')->where('project_id', $p)->find($id);
            abort_unless($doc, 404);
            if ($doc->status === 'posted') {
                return $doc;
            }if ($doc->status !== 'draft') {
                Money::fail('وضعیت سند اجازه تأیید نمی‌دهد.');
            }
            $plan = $this->plan($doc);
            $journal = $this->journal($doc, $plan['lines'], 'approve:'.$id);
            foreach ($plan['allocations'] as $owner => $amount) {
                DB::table('owner_cost_allocations')->insert(['project_id' => $p, 'document_id' => $id, 'rule_version_id' => $plan['rule']->id, 'party_id' => $owner, 'journal_entry_id' => $journal, 'date' => $doc->date, 'amount' => $amount]);
            }
            if ($doc->kind === 'opening') {
                foreach (json_decode($doc->data, true)['checks'] ?? [] as $index => $check) {
                    $incoming = $check['direction'] === 'incoming';
                    $data = $check;
                    unset($data['direction']);
                    if (! $incoming) {
                        $data['allocations'] = [['invoice_id' => $doc->id, 'amount' => Money::amount($check['amount'])]];
                    }
                    $child = DB::table('documents')->insertGetId(['project_id' => $p, 'kind' => $incoming ? 'incoming_check' : 'outgoing_check', 'date' => $doc->date, 'description' => 'چک معوق افتتاحیه: '.$check['number'], 'status' => 'posted', 'idempotency_key' => 'opening-check:'.$id.':'.$index, 'data' => json_encode($data), 'created_by' => $u->id, 'approved_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
                    DB::table('check_events')->insert(['project_id' => $p, 'document_id' => $child, 'journal_entry_id' => $journal, 'event' => 'delivered', 'date' => $doc->date, 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            if (in_array($doc->kind, ['incoming_check', 'outgoing_check'])) {
                DB::table('check_events')->insert(['project_id' => $p, 'document_id' => $id, 'journal_entry_id' => $journal, 'event' => 'delivered', 'date' => $doc->date, 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('documents')->where('id', $id)->update(['status' => 'posted', 'approved_by' => $u->id, 'updated_at' => now()]);
            $this->audit($u, $p, 'document.approved', $id, ['journal' => $journal]);

            return DB::table('documents')->find($id);
        });
    }

    public function reverse(User $u, int $p, int $id, string $date): void
    {
        Validator::make(['date' => $date], ['date' => 'required|date_format:Y-m-d'])->validate();
        $this->locked($u, $p, function () use ($u, $p, $id, $date) {
            $doc = DB::table('documents')->where('project_id', $p)->find($id);
            abort_unless($doc, 404);
            if ($doc->status === 'reversed') {
                return;
            }if ($doc->status !== 'posted') {
                Money::fail('فقط سند تأییدشده قابل برگشت است.');
            }
            if (in_array($doc->kind, ['incoming_check', 'outgoing_check'])) {
                Money::fail('برگشت چک از گردش وضعیت چک انجام می‌شود.');
            }
            if ($doc->kind === 'opening') {
                Money::fail('افتتاحیه نهایی از این مسیر برگشت نمی‌خورد؛ اصلاح نیازمند بررسی دفتر و مانده چک‌ها است.');
            }
            if ($doc->kind === 'invoice') {
                $base = (int) DB::table('invoice_items')->where('document_id', $id)->sum('amount');
                if (-$this->balance($p, 'supplier', null, null, $id) !== $base) {
                    Money::fail('ابتدا تسویه‌های مرتبط را برگشت بزنید.');
                }
            }
            $entries = DB::table('journal_entries')->where('document_id', $id)->pluck('id');
            $lines = DB::table('journal_lines')->whereIn('journal_entry_id', $entries)->get()->map(fn ($l) => $this->line($l->account, $l->credit - $l->debit, $l->party_id, $l->bank_account_id, $l->invoice_id))->all();
            // Reversal cannot make a bank negative or consume a supplier payable already used elsewhere.
            $changes = [];
            foreach ($lines as $l) {
                $key = $l['account'].':'.($l['party_id'] ?? '').':'.($l['bank_account_id'] ?? '').':'.($l['invoice_id'] ?? '');
                $changes[$key] = ($changes[$key] ?? 0) + $l['debit'] - $l['credit'];
            }
            foreach ($lines as $l) {
                $key = $l['account'].':'.($l['party_id'] ?? '').':'.($l['bank_account_id'] ?? '').':'.($l['invoice_id'] ?? '');
                $balance = $this->balance($p, $l['account'], $l['party_id'], $l['bank_account_id'], $l['invoice_id']) + $changes[$key];
                if ($l['account'] === 'bank' && $balance < 0) {
                    Money::fail('برگشت موجب کسری بانک می‌شود.');
                }if (in_array($l['account'], ['supplier', 'fee_payable', 'reimbursement', 'contribution']) && $balance > 0) {
                    Money::fail('برگشت با مانده فعلی حساب سازگار نیست.');
                }
            }
            $j = $this->journal($doc, $lines, 'reverse:'.$id, $date, 'reversal');
            $allocations = DB::table('owner_cost_allocations')->where('document_id', $id)->get();
            foreach ($allocations as $a) {
                DB::table('owner_cost_allocations')->insert(['project_id' => $p, 'document_id' => $id, 'rule_version_id' => $a->rule_version_id, 'party_id' => $a->party_id, 'journal_entry_id' => $j, 'date' => $date, 'amount' => -$a->amount]);
            }
            DB::table('documents')->where('id', $id)->update(['status' => 'reversed', 'updated_at' => now()]);
            $this->audit($u, $p, 'document.reversed', $id, ['journal' => $j]);
        });
    }

    public function checkEvent(User $u, int $p, int $id, string $event, string $date): void
    {
        Validator::make(compact('event', 'date'), ['event' => 'required|in:cleared,returned,cancelled', 'date' => 'required|date_format:Y-m-d'])->validate();
        $this->locked($u, $p, function () use ($u, $p, $id, $event, $date) {
            $doc = DB::table('documents')->where('project_id', $p)->where('status', 'posted')->find($id);
            abort_unless($doc && in_array($doc->kind, ['incoming_check', 'outgoing_check']), 404);
            $last = DB::table('check_events')->where('document_id', $id)->orderByDesc('id')->first();
            if ($last->event === $event) {
                return;
            }if ($last->event !== 'delivered') {
                Money::fail('گردش وضعیت چک مجاز نیست.');
            }if ($date < $last->date) {
                Money::fail('تاریخ رویداد قبل از تحویل چک است.');
            }
            $d = json_decode($doc->data, true);
            $party = (int) $d['party_id'];
            $bank = $this->bank($p, $d['bank_id']);
            $incoming = $doc->kind === 'incoming_check';
            if ($incoming) {
                $a = Money::amount($d['amount']);
                $lines = [$this->line('incoming_check', -$a, $party), $this->line('pending_contribution', $a, $party)];
                if ($event === 'cleared') {
                    $lines[] = $this->line('bank', $a, null, $bank);
                    $lines[] = $this->line('contribution', -$a, $party);
                }
            } else {
                $a = 0;
                $lines = [];
                foreach ($d['allocations'] as $allocation) {
                    $a += Money::amount($allocation['amount']);
                }$lines[] = $this->line('outgoing_check', $a, $party);
                if ($event === 'cleared') {
                    if ($this->balance($p, 'bank', null, $bank) < $a) {
                        Money::fail('موجودی بانک کافی نیست.');
                    }$lines[] = $this->line('bank', -$a, null, $bank);
                } else {
                    foreach ($d['allocations'] as $allocation) {
                        $lines[] = $this->line('supplier', -Money::amount($allocation['amount']), $party, null, (int) $allocation['invoice_id']);
                    }
                }
            }
            $j = $this->journal($doc, $lines, 'check:'.$id.':'.$event, $date, 'check_event');
            DB::table('check_events')->insert(['project_id' => $p, 'document_id' => $id, 'journal_entry_id' => $j, 'event' => $event, 'date' => $date, 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($u, $p, 'check.'.$event, $id, ['journal' => $j]);
        });
    }
}
