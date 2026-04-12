<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_executions', function (Blueprint $table) {
            $table->id();
            $table->string('command', 100);
            $table->string('name', 100);
            $table->enum('status', ['success', 'failed'])->default('success');
            $table->text('output')->nullable();
            $table->decimal('duration_seconds', 8, 2)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['command', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_executions');
    }
};
