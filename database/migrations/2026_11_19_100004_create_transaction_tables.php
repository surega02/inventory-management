<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** loans, asset_movements, maintenances: append-only (rows are added or closed, never deleted). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('borrower_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('loaned_at');
            $table->date('due_date')->nullable();
            $table->timestampTz('returned_at')->nullable();
            $table->string('return_condition', 10)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('returned_recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['borrower_id', 'returned_at']);
        });

        DB::statement("ALTER TABLE loans ADD CONSTRAINT loans_return_condition_check CHECK (return_condition IN ('good', 'fair', 'poor'))");
        DB::statement('ALTER TABLE loans ADD CONSTRAINT loans_returned_after_loaned_check CHECK (returned_at >= loaned_at)');
        DB::statement('CREATE UNIQUE INDEX loans_one_active_per_asset ON loans (asset_id) WHERE returned_at IS NULL');
        DB::statement('CREATE INDEX loans_active_due_date_index ON loans (due_date) WHERE returned_at IS NULL');

        Schema::create('asset_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('from_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->constrained('locations')->restrictOnDelete();
            $table->timestampTz('moved_at');
            $table->string('reason')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE asset_movements ADD CONSTRAINT asset_movements_different_locations_check CHECK (to_location_id <> from_location_id)');

        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->index()->constrained()->restrictOnDelete();
            $table->text('description');
            $table->string('vendor', 150)->nullable();
            $table->date('started_at');
            $table->date('completed_at')->nullable();
            $table->decimal('cost', 14, 2)->nullable();
            $table->string('status', 15)->default('in_progress');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE maintenances ADD CONSTRAINT maintenances_status_check CHECK (status IN ('in_progress', 'done'))");
        DB::statement("CREATE UNIQUE INDEX maintenances_one_in_progress_per_asset ON maintenances (asset_id) WHERE status = 'in_progress'");
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenances');
        Schema::dropIfExists('asset_movements');
        Schema::dropIfExists('loans');
    }
};
