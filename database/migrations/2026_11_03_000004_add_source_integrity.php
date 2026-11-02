<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->createTrigger("CREATE TRIGGER immutable_document BEFORE UPDATE ON documents WHEN OLD.status IN ('posted','reversed') AND (NEW.data<>OLD.data OR NEW.date<>OLD.date OR NEW.kind<>OLD.kind OR NEW.project_id<>OLD.project_id OR NEW.description<>OLD.description OR NEW.idempotency_key<>OLD.idempotency_key OR NEW.created_by<>OLD.created_by OR NEW.status NOT IN ('posted','reversed')) BEGIN SELECT RAISE(ABORT,'Posted source is immutable'); END");
            $this->createTrigger("CREATE TRIGGER immutable_document_delete BEFORE DELETE ON documents WHEN OLD.status IN ('posted','reversed') BEGIN SELECT RAISE(ABORT,'Posted source is immutable'); END");
            foreach (['INSERT', 'UPDATE', 'DELETE'] as $e) {
                $ref = $e === 'INSERT' ? 'NEW' : 'OLD';
                $this->createTrigger('CREATE TRIGGER immutable_item_'.strtolower($e)." BEFORE $e ON invoice_items WHEN (SELECT status FROM documents WHERE id=$ref.document_id)<>'draft' BEGIN SELECT RAISE(ABORT,'Posted invoice items are immutable'); END");
            }
            foreach (['audit_logs', 'check_events', 'rule_versions', 'owner_cost_allocations'] as $table) {
                foreach (['UPDATE', 'DELETE'] as $e) {
                    $this->createTrigger("CREATE TRIGGER append_only_{$table}_".strtolower($e)." BEFORE $e ON $table BEGIN SELECT RAISE(ABORT,'Append only history'); END");
                }
            }
        } elseif (DB::getDriverName() === 'mysql') {
            $this->createTrigger("CREATE TRIGGER immutable_document BEFORE UPDATE ON documents FOR EACH ROW BEGIN IF OLD.status IN ('posted','reversed') AND (NEW.data<>OLD.data OR NEW.date<>OLD.date OR NEW.kind<>OLD.kind OR NEW.project_id<>OLD.project_id OR NEW.description<>OLD.description OR NEW.idempotency_key<>OLD.idempotency_key OR NEW.created_by<>OLD.created_by OR NEW.status NOT IN ('posted','reversed')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted source is immutable'; END IF; END");
            $this->createTrigger("CREATE TRIGGER immutable_document_delete BEFORE DELETE ON documents FOR EACH ROW BEGIN IF OLD.status IN ('posted','reversed') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted source is immutable'; END IF; END");
            foreach (['INSERT', 'UPDATE', 'DELETE'] as $e) {
                $ref = $e === 'INSERT' ? 'NEW' : 'OLD';
                $this->createTrigger('CREATE TRIGGER immutable_item_'.strtolower($e)." BEFORE $e ON invoice_items FOR EACH ROW BEGIN IF (SELECT status FROM documents WHERE id=$ref.document_id)<>'draft' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted invoice items are immutable'; END IF; END");
            }
            foreach (['audit_logs', 'check_events', 'rule_versions', 'owner_cost_allocations'] as $table) {
                foreach (['UPDATE', 'DELETE'] as $e) {
                    $this->createTrigger("CREATE TRIGGER append_only_{$table}_".strtolower($e)." BEFORE $e ON $table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append only history'");
                }
            }
        }
    }

    private function createTrigger(string $sql): void
    {
        preg_match('/CREATE TRIGGER ([a-z_]+)/', $sql, $match);
        $name = $match[1];
        $exists = DB::getDriverName() === 'sqlite'
            ? DB::table('sqlite_master')->where('type', 'trigger')->where('name', $name)->exists()
            : DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::getDatabaseName())->where('TRIGGER_NAME', $name)->exists();
        if (! $exists) {
            DB::unprepared($sql);
        }
    }

    public function down(): void
    {
        foreach (['immutable_document', 'immutable_document_delete', 'immutable_item_insert', 'immutable_item_update', 'immutable_item_delete'] as $name) {
            DB::unprepared("DROP TRIGGER IF EXISTS $name");
        }foreach (['audit_logs', 'check_events', 'rule_versions', 'owner_cost_allocations'] as $table) {
            foreach (['update', 'delete'] as $e) {
                DB::unprepared("DROP TRIGGER IF EXISTS append_only_{$table}_$e");
            }
        }
    }
};
