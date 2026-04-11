<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provisioning_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('recipe_id');
            $table->string('recipe_name');
            $table->string('execution_username')->nullable();
            $table->string('status')->default('pending');
            $table->json('steps')->nullable();
            $table->json('current_step')->nullable();
            $table->text('failure_reason')->nullable();
            $table->float('total_duration')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'status']);
            $table->index(['server_id', 'recipe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provisioning_runs');
    }
};
