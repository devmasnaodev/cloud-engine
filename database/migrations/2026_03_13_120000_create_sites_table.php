<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('domain');
            $table->json('info')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
