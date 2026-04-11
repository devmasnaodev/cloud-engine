<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('ip_address');
            $table->unsignedSmallInteger('ssh_port')->default(22);
            $table->json('ssh_users')->nullable();
            $table->string('ssh_execution_username')->nullable();
            $table->string('provisioning_engine')->nullable()->default(null);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamps();

            $table->index('ip_address');
            $table->index('ssh_execution_username');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
