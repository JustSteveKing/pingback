<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Ping CRM's contacts (REF-0001), each in an account and optionally at one of its
     * organisations, with the revision that gives each one a strong ETag (ADR-0004).
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name', 25);
            $table->string('last_name', 25);
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

            $table->index(['account_id', 'last_name', 'first_name', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
