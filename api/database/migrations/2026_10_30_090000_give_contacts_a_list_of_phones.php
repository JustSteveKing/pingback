<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * A contact's single phone becomes a list (ADR-0007). The API still shows and accepts phone,
     * deprecated, as the first number on the list.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->json('phones')->nullable()->after('email');
        });

        DB::table('contacts')->whereNotNull('phone')->orderBy('id')->each(static function (object $contact): void {
            DB::table('contacts')->where('id', $contact->id)->update([
                'phones' => json_encode([['number' => $contact->phone, 'label' => null]], JSON_THROW_ON_ERROR),
            ]);
        });

        // A contact with no phone has an empty list, never null: clients read phones as a list.
        DB::table('contacts')->whereNull('phone')->update(['phones' => '[]']);

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropColumn('phone');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->string('phone', 50)->nullable()->after('email');
        });

        DB::table('contacts')->whereNotNull('phones')->orderBy('id')->each(static function (object $contact): void {
            $phones = json_decode((string) $contact->phones, true);

            DB::table('contacts')->where('id', $contact->id)->update([
                'phone' => is_array($phones) && is_array($phones[0] ?? null) ? ($phones[0]['number'] ?? null) : null,
            ]);
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropColumn('phones');
        });
    }
};
