<?php

namespace App\Http\Controllers;

use App\Accounting\Access;
use App\Accounting\AccountingService;
use App\Accounting\Exports;
use App\Accounting\Money;
use App\Accounting\ProjectService;
use App\Accounting\Reports;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Reader;

final class AccountingController
{
    public function home(Request $r)
    {
        $projects = DB::table('projects as p')->join('project_members as m', 'm.project_id', '=', 'p.id')->join('organization_members as o', fn ($q) => $q->on('o.organization_id', '=', 'p.organization_id')->on('o.user_id', '=', 'm.user_id'))->where('m.user_id', $r->user()->id)->select('p.*', 'm.role')->get();

        return $r->expectsJson() ? response()->json($projects) : view('accounting.projects', compact('projects'));
    }

    public function createProject(Request $r, ProjectService $s)
    {
        $p = $s->create($r->user(), $r->all());

        return $r->expectsJson() ? response()->json($p, 201) : redirect('/projects/'.$p->id);
    }

    public function show(Request $r, int $project, Reports $reports)
    {
        $p = Access::project($r->user(), $project);
        $role = DB::table('project_members')->where('project_id', $project)->where('user_id', $r->user()->id)->value('role');
        $summary = $reports->summary($project);
        $documents = DB::table('documents')->where('project_id', $project)->when($r->q, fn ($q) => $q->where('description', 'like', '%'.$r->q.'%'))->orderByDesc('id')->paginate(30);
        $parties = DB::table('parties')->where('organization_id', $p->organization_id)->get();
        $banks = DB::table('bank_accounts')->where('project_id', $project)->get();
        $contracts = DB::table('contracts')->where('project_id', $project)->get();
        $rules = DB::table('rule_versions')->where('project_id', $project)->orderByDesc('id')->get();
        $categories = DB::table('expense_categories')->where('project_id', $project)->get();
        $invoices = DB::table('documents')->where('project_id', $project)->whereIn('kind', ['invoice', 'opening'])->where('status', 'posted')->get();
        $checks = DB::table('documents')->where('project_id', $project)->whereIn('kind', ['incoming_check', 'outgoing_check'])->where('status', 'posted')->get();
        foreach ($checks as $c) {
            $c->last_event = DB::table('check_events')->where('document_id', $c->id)->orderByDesc('id')->value('event');
        }
        $contractAttachments = DB::table('attachments')->where('project_id', $project)->whereNotNull('contract_id')->get();
        $phases = DB::table('construction_phases')->where('project_id', $project)->get();
        $funding = DB::table('funding_schedule_items')->where('project_id', $project)->orderBy('due_date')->get();
        $fiscals = DB::table('fiscal_years')->where('project_id', $project)->get();
        $members = DB::table('project_members as m')->join('users as u', 'u.id', '=', 'm.user_id')->where('m.project_id', $project)->select('u.name', 'u.email', 'm.role')->get();

        if ($r->expectsJson()) {
            return response()->json(compact('p', 'role', 'summary', 'documents', 'parties', 'banks', 'contracts', 'rules', 'categories', 'invoices', 'checks', 'fiscals', 'phases', 'funding'));
        }

        return view('accounting.project', compact('p', 'role', 'summary', 'documents', 'parties', 'banks', 'contracts', 'rules', 'categories', 'invoices', 'checks', 'fiscals', 'members', 'phases', 'funding', 'contractAttachments'));
    }

    public function resource(Request $r, int $project, string $resource, AccountingService $s)
    {
        $p = Access::project($r->user(), $project, true, in_array($resource, ['members', 'fiscal', 'rules', 'contracts', 'funding']));
        if ($resource === 'rules') {
            $d = $r->validate(['payload' => 'required|json']);
            $result = app(ProjectService::class)->rule($r->user(), $project, json_decode($d['payload'], true), $r->boolean('approve'));

            return response()->json($result);
        }
        $s->locked($r->user(), $project, function () use ($r, $project, $resource, $p, $s) {
            switch ($resource) {
                case 'parties':$d = $r->validate(['name' => 'required|string|max:150', 'identity_code' => 'nullable|string|max:40', 'phone' => 'nullable|string|max:40']);
                    if (! empty($d['identity_code']) && DB::table('parties')->where('organization_id', $p->organization_id)->where('identity_code', $d['identity_code'])->exists()) {
                        Money::fail('هویت شخص قبلاً ثبت شده است.');
                    }DB::table('parties')->insert($d + ['organization_id' => $p->organization_id, 'created_at' => now(), 'updated_at' => now()]);
                    break;
                case 'banks':$d = $r->validate(['name' => 'required|string|max:150', 'number' => 'nullable|string|max:100']);
                    DB::table('bank_accounts')->insert($d + ['project_id' => $project, 'created_at' => now(), 'updated_at' => now()]);
                    break;
                case 'contracts':$d = $r->validate(['number' => 'required|string|max:100', 'kind' => 'required|in:ownership,executor,supplier,amendment,minutes', 'date' => 'required|date_format:Y-m-d', 'description' => 'required|string|max:2000']);
                    if (DB::table('contracts')->where('project_id', $project)->where('number', $d['number'])->exists()) {
                        Money::fail('شماره قرارداد تکراری است.');
                    }DB::table('contracts')->insert($d + ['project_id' => $project, 'created_at' => now(), 'updated_at' => now()]);
                    break;
                case 'categories':$d = $r->validate(['name' => 'required|string|max:150', 'parent_id' => 'nullable|integer']);
                    $parent = $d['parent_id'] ?? null;
                    $level = 1;
                    while ($parent) {
                        $cat = DB::table('expense_categories')->where('project_id', $project)->find($parent);
                        if (! $cat || ++$level > 3) {
                            Money::fail('سرفصل حداکثر سه سطح دارد.');
                        }$parent = $cat->parent_id;
                    }DB::table('expense_categories')->insert($d + ['project_id' => $project]);
                    break;
                case 'fiscal':$d = $r->validate(['name' => 'required|string|max:30', 'starts_on' => 'required|date_format:Y-m-d', 'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on']);
                    if (DB::table('fiscal_years')->where('project_id', $project)->where('starts_on', '<=', $d['ends_on'])->where('ends_on', '>=', $d['starts_on'])->exists()) {
                        Money::fail('سال مالی با دوره موجود همپوشانی دارد.');
                    }DB::table('fiscal_years')->insert($d + ['project_id' => $project]);
                    break;
                case 'phases':$d = $r->validate(['name' => 'required|string|max:150']);
                    DB::table('construction_phases')->insert(['project_id' => $project, 'name' => $d['name']]);
                    break;
                case 'funding':$d = $r->validate(['party_id' => 'required|integer', 'contract_id' => 'required|integer', 'due_date' => 'required|date_format:Y-m-d', 'amount' => 'required', 'legally_due' => 'nullable|boolean', 'description' => 'required|string|max:255']);
                    $s->owner($project, $d['party_id'], $d['due_date']);
                    if (! DB::table('contracts')->where('project_id', $project)->where('id', $d['contract_id'])->exists()) {
                        Money::fail('قرارداد نامعتبر است.');
                    }$d['amount'] = Money::amount($d['amount']);
                    $d['legally_due'] = $r->boolean('legally_due');
                    DB::table('funding_schedule_items')->insert($d + ['project_id' => $project, 'created_at' => now(), 'updated_at' => now()]);
                    break;
                case 'members':$d = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:200', 'password' => 'nullable|string|min:12', 'role' => 'required|in:manager,accountant,owner']);
                    $user = User::where('email', $d['email'])->first();
                    if (! $user) {
                        if (empty($d['password'])) {
                            Money::fail('برای کاربر جدید رمز اولیه لازم است.');
                        }$user = User::create(['name' => $d['name'], 'email' => $d['email'], 'password' => $d['password']]);
                    } else {
                        if (! DB::table('organization_members')->where('organization_id', $p->organization_id)->where('user_id', $user->id)->exists()) {
                            Money::fail('افزودن کاربر موجود خارج از سازمان نیازمند فرایند دعوت است.');
                        }
                    }if ($user->id === $r->user()->id) {
                        Money::fail('نقش خودتان را از این مسیر تغییر ندهید.');
                    }DB::table('organization_members')->insertOrIgnore(['organization_id' => $p->organization_id, 'user_id' => $user->id, 'role' => $d['role'] === 'manager' ? 'manager' : 'member']);
                    DB::table('project_members')->updateOrInsert(['project_id' => $project, 'user_id' => $user->id], ['role' => $d['role']]);
                    break;
                default:abort(404);
            }$s->audit($r->user(), $project, 'settings.'.$resource, null);
        });

        return $r->expectsJson() ? response()->json(['saved' => true], 201) : back()->with('success', 'اطلاعات ذخیره شد.');
    }

    public function closeFiscal(Request $r, int $project, int $fiscal, AccountingService $s)
    {
        $s->locked($r->user(), $project, function () use ($r, $project, $fiscal, $s) {
            $fy = DB::table('fiscal_years')->where('project_id', $project)->find($fiscal);
            abort_unless($fy, 404);
            DB::table('fiscal_years')->where('id', $fiscal)->update(['closed' => true]);
            $s->audit($r->user(), $project, 'fiscal.closed', null, ['fiscal' => $fiscal]);
        }, true);

        return back()->with('success', 'سال مالی بسته شد.');
    }

    public function draft(Request $r, int $project, AccountingService $s)
    {
        $input = $r->all();
        if (isset($input['payload'])) {
            $r->validate(['payload' => 'required|json']);
            $input = json_decode($input['payload'], true);
        } $doc = $s->draft($r->user(), $project, $input);

        return $r->expectsJson() ? response()->json($doc, 201) : redirect('/projects/'.$project.'/documents/'.$doc->id);
    }

    public function document(Request $r, int $project, int $document, AccountingService $s)
    {
        Access::project($r->user(), $project);
        $doc = DB::table('documents')->where('project_id', $project)->find($document);
        abort_unless($doc, 404);
        $plan = $doc->status === 'draft' ? $s->plan($doc) : null;
        $entries = DB::table('journal_entries')->where('document_id', $document)->get();
        $lines = DB::table('journal_lines')->where('project_id', $project)->whereIn('journal_entry_id', $entries->pluck('id'))->get();
        $attachments = DB::table('attachments')->where('project_id', $project)->where('document_id', $document)->get();
        $role = DB::table('project_members')->where('project_id', $project)->where('user_id', $r->user()->id)->value('role');

        return $r->expectsJson() ? response()->json(compact('doc', 'plan', 'entries', 'lines')) : view('accounting.document', compact('doc', 'plan', 'entries', 'lines', 'attachments', 'role'));
    }

    public function approve(Request $r, int $project, int $document, AccountingService $s)
    {
        $doc = $s->approve($r->user(), $project, $document);

        return $r->expectsJson() ? response()->json($doc) : back()->with('success', 'سند تأیید و در دفتر ثبت شد.');
    }

    public function reverse(Request $r, int $project, int $document, AccountingService $s)
    {
        $r->validate(['date' => 'required|date_format:Y-m-d']);
        $s->reverse($r->user(), $project, $document, $r->date);

        return $r->expectsJson() ? response()->json(['reversed' => true]) : back()->with('success', 'سند معکوس ثبت شد.');
    }

    public function checkEvent(Request $r, int $project, int $document, AccountingService $s)
    {
        $r->validate(['event' => 'required|string', 'date' => 'required|date_format:Y-m-d']);
        $s->checkEvent($r->user(), $project, $document, $r->event, $r->date);

        return $r->expectsJson() ? response()->json(['saved' => true]) : back()->with('success', 'رویداد چک ثبت شد.');
    }

    public function report(Request $r, int $project, Reports $s)
    {
        $p = Access::project($r->user(), $project);
        $r->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d', 'account' => 'nullable|string', 'party_id' => 'nullable|integer', 'bank_account_id' => 'nullable|integer']);
        $summary = $s->summary($project, $r->from, $r->to);
        $details = $s->details($project, $r->from, $r->to);
        $ledger = $s->ledger($project, $r->only(['account', 'party_id', 'bank_account_id', 'from', 'to']));
        $audit = DB::table('audit_logs')->where('project_id', $project)->orderByDesc('id')->limit(200)->get();
        if ($r->export === 'xlsx') {
            $path = app(Exports::class)->xlsx(app(Exports::class)->ledgerRows($ledger));

            return response()->download($path, 'ledger.xlsx')->deleteFileAfterSend(true);
        }
        if ($r->export === 'pdf') {
            return response(app(Exports::class)->pdf(compact('p', 'summary', 'ledger')), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="financial-report.pdf"']);
        }
        if ($r->export === 'csv') {
            return response()->streamDownload(function () use ($ledger) {
                $f = fopen('php://output', 'w');
                fwrite($f, "\xEF\xBB\xBF");
                fputcsv($f, ['date', 'number', 'account', 'party_id', 'bank_id', 'debit_rial', 'credit_rial', 'description'], ',', '"', '');
                foreach ($ledger as $l) {
                    $description = preg_match('/^[=+\-@\t\r]/u', $l->description) ? "'".$l->description : $l->description;
                    fputcsv($f, [$l->date, $l->number, $l->account, $l->party_id, $l->bank_account_id, $l->debit, $l->credit, $description], ',', '"', '');
                }fclose($f);
            }, 'ledger.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return $r->expectsJson() ? response()->json(compact('summary', 'ledger', 'details')) : view('accounting.report', compact('p', 'summary', 'ledger', 'audit', 'details'));
    }

    public function upload(Request $r, int $project, int $document)
    {
        Access::project($r->user(), $project, true);
        abort_unless(DB::table('documents')->where('project_id', $project)->where('id', $document)->exists(), 404);
        $r->validate(['file' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png']);
        $file = $r->file('file');
        $path = $file->store('attachments', 'local');
        DB::table('attachments')->insert(['project_id' => $project, 'document_id' => $document, 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime' => $file->getMimeType(), 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'پیوست به‌صورت خصوصی ذخیره شد.');
    }

    public function attachment(Request $r, int $project, int $attachment)
    {
        Access::project($r->user(), $project);
        $a = DB::table('attachments')->where('project_id', $project)->find($attachment);
        abort_unless($a, 404);

        return Storage::disk('local')->download($a->path, $a->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function template(Request $r, int $project)
    {
        Access::project($r->user(), $project);
        $rows = [['account', 'debit', 'credit', 'party_id', 'bank_id']];
        if ($r->export === 'xlsx') {
            $path = app(Exports::class)->xlsx($rows, 'opening-template');

            return response()->download($path, 'opening-template.xlsx')->deleteFileAfterSend(true);
        }

        return response("account,debit,credit,party_id,bank_id\n", 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="opening-template.csv"']);
    }

    public function import(Request $r, int $project, AccountingService $s)
    {
        Access::project($r->user(), $project, true);
        $r->validate(['file' => 'required|file|max:2048|extensions:csv,xlsx']);
        $file = $r->file('file');
        $hash = hash_file('sha256', $file->getRealPath());
        if (DB::table('import_batches')->where('project_id', $project)->where('hash', $hash)->exists()) {
            Money::fail('فایل تکراری قبلاً بررسی شده است.');
        }
        $parsed = [];
        $errors = [];
        if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
            $zip = new \ZipArchive;
            if ($zip->open($file->getRealPath()) !== true) {
                Money::fail('فایل Excel نامعتبر است.');
            }$size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $size += $zip->statIndex($i)['size'];
            }$zip->close();
            if ($size > 20 * 1024 * 1024) {
                Money::fail('حجم بازشده فایل بیش از حد مجاز است.');
            }
            $reader = new Reader;
            try {
                $reader->open($file->getRealPath());
                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $row) {
                        $cells = [];
                        foreach ($row->getCells() as $cell) {
                            $value = $cell->getValue();
                            if ($cell instanceof FormulaCell || is_float($value) || is_object($value)) {
                                $errors[] = 'از فرمول، اعشار و تاریخ قالب‌بندی‌شده در ستون مبلغ استفاده نکنید.';
                                $value = 'INVALID';
                            }$cells[] = (string) ($value ?? '');
                        }$parsed[] = $cells;
                        if (count($parsed) > 501) {
                            $errors[] = 'حداکثر ۵۰۰ ردیف مجاز است.';
                            break;
                        }
                    }break;
                }
            } catch (\Throwable $e) {
                $errors[] = 'ساختار فایل Excel قابل خواندن نیست.';
            } finally {
                $reader->close();
            }
        } else {
            $f = fopen($file->getRealPath(), 'r');
            while (($row = fgetcsv($f, 0, ',', '"', '')) !== false) {
                $parsed[] = $row;
                if (count($parsed) > 501) {
                    $errors[] = 'حداکثر ۵۰۰ ردیف مجاز است.';
                    break;
                }
            }fclose($f);
        }
        $header = array_shift($parsed) ?? [];
        if ($header) {
            $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
        }$rows = [];
        if ($header !== ['account', 'debit', 'credit', 'party_id', 'bank_id']) {
            $errors[] = 'ستون‌های فایل با الگو تطابق ندارند.';
        } else {
            foreach ($parsed as $index => $row) {
                if (count($row) !== 5) {
                    $errors[] = 'تعداد ستون نامعتبر در ردیف '.($index + 2);

                    continue;
                }$rows[] = array_combine($header, $row);
            }
        }
        if (! $errors) {
            try {
                $p = DB::table('projects')->find($project);
                $doc = (object) ['id' => 0, 'project_id' => $project, 'kind' => 'opening', 'date' => $p->commencement_date, 'data' => json_encode(['reconciled' => true, 'lines' => $rows])];
                $s->plan($doc);
            } catch (ValidationException $e) {
                $errors = array_merge(...array_values($e->errors()));
            }
        }
        $id = DB::table('import_batches')->insertGetId(['project_id' => $project, 'hash' => $hash, 'rows' => json_encode($rows), 'errors' => json_encode($errors), 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        $importErrors = $errors;

        return $r->expectsJson() ? response()->json(compact('id', 'rows', 'importErrors')) : view('accounting.import', compact('id', 'project', 'rows', 'importErrors'));
    }

    public function approveImport(Request $r, int $project, int $batch, AccountingService $s)
    {
        $r->validate(['reconciled' => 'accepted']);
        $doc = $s->locked($r->user(), $project, function ($p) use ($r, $project, $batch, $s) {
            $b = DB::table('import_batches')->where('project_id', $project)->find($batch);
            abort_unless($b, 404);
            if (json_decode($b->errors, true)) {
                Money::fail('فایل دارای خطا است.');
            }if ($b->document_id) {
                return DB::table('documents')->find($b->document_id);
            }$doc = $s->draft($r->user(), $project, ['kind' => 'opening', 'date' => $p->commencement_date, 'description' => 'افتتاحیه از فایل واردشده', 'idempotency_key' => 'import:'.$b->hash, 'data' => ['reconciled' => true, 'lines' => json_decode($b->rows, true)]]);
            DB::table('import_batches')->where('id', $batch)->update(['document_id' => $doc->id]);

            return $doc;
        });

        return redirect('/projects/'.$project.'/documents/'.$doc->id);
    }

    public function uploadContract(Request $r, int $project, int $contract, AccountingService $s)
    {
        Access::project($r->user(), $project, true, true);
        abort_unless(DB::table('contracts')->where('project_id', $project)->where('id', $contract)->exists(), 404);
        $r->validate(['file' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png']);
        $f = $r->file('file');
        $path = $f->store('attachments', 'local');
        DB::table('attachments')->insert(['project_id' => $project, 'contract_id' => $contract, 'path' => $path, 'original_name' => $f->getClientOriginalName(), 'mime' => $f->getMimeType(), 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        $s->audit($r->user(), $project, 'contract.attachment', null, ['contract' => $contract]);

        return back()->with('success','پیوست قرارداد خصوصی ذخیره شد.');
    }
}
