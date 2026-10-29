<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Ping CRM's demo account and user, so a fresh install has something to call and the
        // SDK's contract suite has someone to sign in as. Model events are off while seeding, so
        // the account is made here rather than by the registration listener.
        $owner = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => 'secret-pingback-demo',
        ]);

        $account = Account::factory()->for($owner, 'owner')->create(['name' => 'Acme Corporation']);

        // Ping CRM seeds a hundred organisations, enough that a list has more than one page.
        $organizations = Organization::factory()->count(100)->for($account)->create();

        // And a hundred contacts, each at one of those organisations, as Ping CRM seeds them.
        Contact::factory()
            ->count(100)
            ->for($account)
            ->sequence(static fn(): array => ['organization_id' => $organizations->random()->id])
            ->create();
    }
}
