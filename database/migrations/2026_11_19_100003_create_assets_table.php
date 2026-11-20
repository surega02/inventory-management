<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('available')->index();
            $table->string('condition', 10)->default('good');
            $table->string('brand', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 14, 2)->nullable();
            $table->smallInteger('useful_life_years')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('category_id');
            $table->index('location_id');
        });

        DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_status_check CHECK (status IN ('available', 'borrowed', 'maintenance', 'broken', 'disposed'))");
        DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_condition_check CHECK (condition IN ('good', 'fair', 'poor'))");
        DB::statement('CREATE UNIQUE INDEX assets_serial_number_unique ON assets (serial_number) WHERE serial_number IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
