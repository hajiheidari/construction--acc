<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_phases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained();
            $t->string('name');
            $t->unique(['id', 'project_id']);
        });
        Schema::table('invoice_items', function (Blueprint $t) {
            $t->unsignedBigInteger('phase_id')->nullable();
            $t->foreign(['phase_id', 'project_id'])->references(['id', 'project_id'])->on('construction_phases');
            $t->string('quantity', 30)->nullable();
            $t->string('unit', 30)->nullable();
            $t->bigInteger('unit_price')->nullable();
            $t->bigInteger('discount')->default(0);
        });
        Schema::create('funding_schedule_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained();
            $t->foreignId('party_id')->constrained('parties');
            $t->unsignedBigInteger('contract_id');
            $t->foreign(['contract_id', 'project_id'])->references(['id', 'project_id'])->on('contracts');
            $t->date('due_date');
            $t->bigInteger('amount');
            $t->boolean('legally_due')->default(false);
            $t->string('description');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_schedule_items');
        Schema::table('invoice_items', function (Blueprint $t) {
            $t->dropForeign(['phase_id', 'project_id']);
            $t->dropColumn(['phase_id', 'quantity', 'unit', 'unit_price', 'discount']);
        });
        Schema::dropIfExists('construction_phases');
    }
};
