<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->account = Account::factory()->create();
    $this->token = contactToken($this->account);
});

it('keeps a list of phones, and shows the first as the deprecated phone', function (): void {
    $this->withToken($this->token)
        ->postJson('/v1/contacts', ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'phones' => [
            ['number' => '+44 20 7946 0000', 'label' => 'work'],
            ['number' => '+44 7700 900000'],
        ]])
        ->assertCreated()
        ->assertJsonPath('data.phones', [
            ['number' => '+44 20 7946 0000', 'label' => 'work'],
            ['number' => '+44 7700 900000', 'label' => null],
        ])
        ->assertJsonPath('data.phone', '+44 20 7946 0000');
});

it('still takes the deprecated phone, as a list of one', function (): void {
    $this->withToken($this->token)
        ->postJson('/v1/contacts', ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone' => '+44 20 7946 0000'])
        ->assertCreated()
        ->assertJsonPath('data.phones', [['number' => '+44 20 7946 0000', 'label' => null]])
        ->assertJsonPath('data.phone', '+44 20 7946 0000');
});

it('refuses phone and phones together', function (): void {
    $this->withToken($this->token)
        ->postJson('/v1/contacts', ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone' => '+44 20 7946 0000', 'phones' => []])
        ->assertProblem(422)
        ->assertJsonPath('errors.phone.0', __('api.contacts.phone_with_phones'));
});

it('replaces the list when a patch sets phone, and empties it when phone is null', function (): void {
    $contact = Contact::factory()->for($this->account)->create(['phones' => [['number' => '+1 555 0100', 'label' => 'work'], ['number' => '+1 555 0101', 'label' => null]]]);

    mergePatch("/v1/contacts/{$contact->id}", ['phone' => '+1 555 0199'], $this->token, ['If-Match' => contactEtag($contact, $this->token)])
        ->assertOk()
        ->assertJsonPath('data.phones', [['number' => '+1 555 0199', 'label' => null]]);

    mergePatch("/v1/contacts/{$contact->id}", ['phone' => null], $this->token, ['If-Match' => contactEtag($contact, $this->token)])
        ->assertOk()
        ->assertJsonPath('data.phones', [])
        ->assertJsonPath('data.phone', null);
});

it('replaces the whole list when a patch sets phones, as merge patch replaces arrays', function (): void {
    $contact = Contact::factory()->for($this->account)->create(['phones' => [['number' => '+1 555 0100', 'label' => 'work']]]);

    mergePatch("/v1/contacts/{$contact->id}", ['phones' => [['number' => '+1 555 0177', 'label' => 'mobile']]], $this->token, ['If-Match' => contactEtag($contact, $this->token)])
        ->assertOk()
        ->assertJsonPath('data.phones', [['number' => '+1 555 0177', 'label' => 'mobile']])
        ->assertJsonPath('data.phone', '+1 555 0177');
});

it('refuses phone and phones together in a patch', function (): void {
    $contact = Contact::factory()->for($this->account)->create();

    mergePatch("/v1/contacts/{$contact->id}", ['phone' => '+1 555 0199', 'phones' => []], $this->token, ['If-Match' => contactEtag($contact, $this->token)])
        ->assertProblem(422)
        ->assertJsonValidationErrors('phone');
});

it('refuses a malformed phone or list of phones', function (array $body, string $field): void {
    $this->withToken($this->token)
        ->postJson('/v1/contacts', ['first_name' => 'Ada', 'last_name' => 'Lovelace', ...$body])
        ->assertProblem(422)
        ->assertJsonValidationErrors($field);
})->with([
    'phone not a string' => [['phone' => ['+1 555 0100']], 'phone'],
    'a phone with no number' => [['phones' => [['label' => 'work']]], 'phones.0.number'],
    'a phone with an unknown member' => [['phones' => [['number' => '+1 555 0100', 'extension' => '12']]], 'phones.0'],
    'more than ten' => [['phones' => array_fill(0, 11, ['number' => '+1 555 0100'])], 'phones'],
]);

it('moves each existing phone onto the list when it migrates', function (): void {
    $migration = require database_path('migrations/2026_10_29_160000_give_contacts_a_list_of_phones.php');
    $migration->down();

    DB::table('contacts')->insert([
        ['id' => (string) Str::ulid(), 'account_id' => $this->account->id, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone' => '+1 555 0100', 'revision' => 1],
        ['id' => (string) Str::ulid(), 'account_id' => $this->account->id, 'first_name' => 'Grace', 'last_name' => 'Hopper', 'phone' => null, 'revision' => 1],
    ]);

    $migration->up();

    expect(Contact::query()->where('first_name', 'Ada')->sole()->phones)->toBe([['number' => '+1 555 0100', 'label' => null]])
        ->and(Contact::query()->where('first_name', 'Grace')->sole()->phones)->toBe([]);
});
