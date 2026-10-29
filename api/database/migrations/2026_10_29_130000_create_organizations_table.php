<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Ping CRM's organisations (REF-0001), with ULIDs and a revision every write increments, so
     * each one has a strong ETag for If-Match (ADR-0004).
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('email', 50)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address', 150)->nullable();
            $table->string('city', 50)->nullable();
            $table->string('region', 50)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('postal_code', 25)->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'name', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
