<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('message');
            $table->jsonb('tool_calls')->default('[]');
            $table->string('status', 15);
            $table->string('model', 60);
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });

        DB::statement("ALTER TABLE chat_logs ADD CONSTRAINT chat_logs_status_check CHECK (status IN ('ok', 'refused', 'error', 'rate_limited'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_logs');
    }
};
