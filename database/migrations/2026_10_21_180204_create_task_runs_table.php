<?php

use App\Enums\TaskRunStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('task_runs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('name');
            $table->string('status', 20)->default(TaskRunStatus::Running->value);
            $table->string('queue')->nullable();
            $table->uuid('job_uuid')->nullable()->unique();
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['type', 'name', 'started_at']);
            $table->index(['status', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_runs');
    }
};
