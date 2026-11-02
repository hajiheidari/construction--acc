<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('organization_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('role');
            $t->unique(['organization_id', 'user_id']);
        });
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->string('name');
            $t->string('code');
            $t->text('address')->nullable();
            $t->date('commencement_date');
            $t->string('history_mode')->default('opening');
            $t->string('status')->default('active');
            $t->bigInteger('budget')->default(0);
            $t->timestamps();
            $t->unique(['organization_id', 'code']);
            $t->unique(['id', 'organization_id']);
        });
        Schema::create('project_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('role');
            $t->unique(['project_id', 'user_id']);
        });
        Schema::create('parties', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->string('name');
            $t->string('identity_code')->nullable();
            $t->string('phone')->nullable();
            $t->timestamps();
            $t->unique(['organization_id', 'identity_code']);
            $t->unique(['id', 'organization_id']);
        });
        Schema::create('contracts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained();
            $t->string('number');
            $t->string('kind');
            $t->date('date');
            $t->text('description');
            $t->timestamps();
            $t->unique(['project_id', 'number']);
            $t->unique(['id', 'project_id']);
        });
        Schema::create('rule_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained();
            $t->unsignedBigInteger('contract_id');
            $t->foreign(['contract_id', 'project_id'])->references(['id', 'project_id'])->on('contracts');
            $t->date('effective_date');
            $t->unsignedInteger('fee_bps');
            $t->json('shares');
            $t->string('mode');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->index(['project_id', 'effective_date']);
        });
        Schema::create('fiscal_years', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained();
            $t->string('name');
            $t->date('starts_on');
            $t->date('ends_on');
            $t->boolean('closed')->default(false);
            $t->unique(['project_id', 'name']);
            $t->unique(['id', 'project_id']);
        });
        Schema::create('bank_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained();
            $t->string('name');
            $t->string('number')->nullable();
            $t->timestamps();
            $t->unique(['id', 'project_id']);
        });
        Schema::create('expense_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained();
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->string('name');
            $t->unique(['id', 'project_id']);
            $t->foreign(['parent_id', 'project_id'])->references(['id', 'project_id'])->on('expense_categories');
        });
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained();
            $t->string('kind');
            $t->date('date');
            $t->string('reference')->nullable();
            $t->string('description');
            $t->string('status')->default('draft');
            $t->string('idempotency_key', 100);
            $t->json('data');
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->timestamps();
            $t->unique(['project_id', 'idempotency_key']);
            $t->unique(['id', 'project_id']);
            $t->index(['project_id', 'date', 'kind', 'status']);
        });
        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('document_id');
            $t->unsignedBigInteger('project_id');
            $t->foreign(['document_id', 'project_id'])->references(['id', 'project_id'])->on('documents');
            $t->string('description');
            $t->unsignedBigInteger('category_id')->nullable();
            $t->foreign(['category_id', 'project_id'])->references(['id', 'project_id'])->on('expense_categories');
            $t->bigInteger('amount');
            $t->boolean('fee_eligible')->default(true);
        });
        Schema::create('document_sequences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained();
            $t->foreignId('fiscal_year_id')->constrained();
            $t->string('kind');
            $t->unsignedBigInteger('next_number')->default(1);
            $t->unique(['project_id', 'fiscal_year_id', 'kind'], 'sequence_scope');
        });
        Schema::create('journal_entries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('document_id');
            $t->foreign(['document_id', 'project_id'])->references(['id', 'project_id'])->on('documents');
            $t->unsignedBigInteger('fiscal_year_id');
            $t->foreign(['fiscal_year_id', 'project_id'])->references(['id', 'project_id'])->on('fiscal_years');
            $t->unsignedBigInteger('number');
            $t->string('kind');
            $t->date('date');
            $t->string('description');
            $t->string('posting_key');
            $t->string('status')->default('draft');
            $t->timestamp('created_at');
            $t->unique(['project_id', 'posting_key']);
            $t->unique(['project_id', 'fiscal_year_id', 'kind', 'number'], 'journal_number');
            $t->unique(['id', 'project_id']);
        });
        Schema::create('journal_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('journal_entry_id');
            $t->foreign(['journal_entry_id', 'project_id'])->references(['id', 'project_id'])->on('journal_entries');
            $t->string('account');
            $t->unsignedBigInteger('party_id')->nullable();
            $t->unsignedBigInteger('bank_account_id')->nullable();
            $t->unsignedBigInteger('invoice_id')->nullable();
            $t->bigInteger('debit')->default(0);
            $t->bigInteger('credit')->default(0);
            $t->index(['project_id', 'account', 'party_id']);
            $t->index(['project_id', 'bank_account_id']);
        });
        Schema::create('owner_cost_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained();
            $t->foreignId('document_id')->constrained();
            $t->foreignId('rule_version_id')->constrained('rule_versions');
            $t->foreignId('party_id')->constrained('parties');
            $t->foreignId('journal_entry_id')->constrained();
            $t->date('date');
            $t->bigInteger('amount');
            $t->index(['project_id', 'party_id']);
        });
        Schema::create('check_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained();
            $t->foreignId('document_id')->constrained();
            $t->foreignId('journal_entry_id')->nullable()->constrained();
            $t->string('event');
            $t->date('date');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('event');
            $t->unsignedBigInteger('document_id')->nullable();
            $t->json('details');
            $t->timestamp('created_at');
            $t->index(['project_id', 'created_at']);
        });
        Schema::create('attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained();
            $t->foreignId('document_id')->nullable()->constrained();
            $t->foreignId('contract_id')->nullable()->constrained();
            $t->string('path');
            $t->string('original_name');
            $t->string('mime');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('import_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained();
            $t->string('hash', 64);
            $t->json('rows');
            $t->json('errors');
            $t->foreignId('document_id')->nullable()->constrained();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->unique(['project_id', 'hash']);
        });
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER immutable_entry_update BEFORE UPDATE ON journal_entries WHEN OLD.status = 'posted' BEGIN SELECT RAISE(ABORT, 'Posted journal is immutable'); END");
            DB::unprepared("CREATE TRIGGER immutable_entry_delete BEFORE DELETE ON journal_entries WHEN OLD.status = 'posted' BEGIN SELECT RAISE(ABORT, 'Posted journal is immutable'); END");
            foreach (['INSERT', 'UPDATE', 'DELETE'] as $event) {
                $ref = $event === 'INSERT' ? 'NEW' : 'OLD';
                DB::unprepared('CREATE TRIGGER immutable_line_'.strtolower($event)." BEFORE $event ON journal_lines WHEN (SELECT status FROM journal_entries WHERE id=$ref.journal_entry_id)='posted' BEGIN SELECT RAISE(ABORT, 'Posted journal is immutable'); END");
            }
            DB::unprepared("CREATE TRIGGER balanced_post BEFORE UPDATE ON journal_entries WHEN NEW.status='posted' AND (NOT EXISTS(SELECT 1 FROM journal_lines WHERE journal_entry_id=NEW.id) OR (SELECT SUM(debit-credit) FROM journal_lines WHERE journal_entry_id=NEW.id)<>0) BEGIN SELECT RAISE(ABORT, 'Unbalanced journal'); END");
        } elseif ($driver === 'mysql') {
            foreach (['UPDATE', 'DELETE'] as $event) {
                DB::unprepared('CREATE TRIGGER immutable_entry_'.strtolower($event)." BEFORE $event ON journal_entries FOR EACH ROW BEGIN IF OLD.status='posted' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted journal is immutable'; END IF; END");
            }
            foreach (['INSERT', 'UPDATE', 'DELETE'] as $event) {
                $ref = $event === 'INSERT' ? 'NEW' : 'OLD';
                DB::unprepared('CREATE TRIGGER immutable_line_'.strtolower($event)." BEFORE $event ON journal_lines FOR EACH ROW BEGIN IF (SELECT status FROM journal_entries WHERE id=$ref.journal_entry_id)='posted' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted journal is immutable'; END IF; END");
            }
            DB::unprepared("CREATE TRIGGER balanced_post BEFORE UPDATE ON journal_entries FOR EACH ROW BEGIN IF NEW.status='posted' AND (NOT EXISTS(SELECT 1 FROM journal_lines WHERE journal_entry_id=NEW.id) OR (SELECT COALESCE(SUM(debit-credit),0) FROM journal_lines WHERE journal_entry_id=NEW.id)<>0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unbalanced journal'; END IF; END");
        }
    }

    public function down(): void
    {
        foreach (['import_batches', 'attachments', 'audit_logs', 'check_events', 'owner_cost_allocations', 'journal_lines', 'journal_entries', 'document_sequences', 'invoice_items', 'documents', 'expense_categories', 'bank_accounts', 'fiscal_years', 'rule_versions', 'contracts', 'parties', 'project_members', 'projects', 'organization_members', 'organizations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
