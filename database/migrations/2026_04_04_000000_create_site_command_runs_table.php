<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_command_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            $table->string('domain', 255);
            $table->text('command');
            $table->json('parameters')->nullable();
            $table->string('status', 20)->default('pending');
            $table->longText('stdout')->nullable();
            $table->text('stderr')->nullable();
            $table->integer('exit_status')->nullable();
            $table->text('partial_stdout')->nullable();
            $table->float('duration')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_command_runs');
    }
};
