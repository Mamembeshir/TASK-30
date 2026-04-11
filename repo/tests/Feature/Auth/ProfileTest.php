<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Livewire\Auth\Profile;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\EncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders profile page for authenticated user', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Jane', 'last_name' => 'Doe']);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->assertOk()
        ->assertSet('firstName', 'Jane')
        ->assertSet('lastName',  'Doe');
});

it('redirects unauthenticated user from profile to login', function () {
    $this->get(route('profile'))->assertRedirect(route('login'));
});

it('validates required first/last name and rejects future date of birth', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Jane', 'last_name' => 'Doe']);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('firstName', '')
        ->set('lastName',  '')
        ->set('dateOfBirth', now()->addYear()->toDateString())
        ->call('save')
        ->assertHasErrors(['firstName' => 'required', 'lastName' => 'required', 'dateOfBirth' => 'before']);
});

it('rejects ssn fragment that is not exactly 4 digits', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Jane', 'last_name' => 'Doe']);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('ssnFragment', '12')
        ->call('save')
        ->assertHasErrors('ssnFragment');
});

it('encrypts sensitive fields and stores a mask', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Jane', 'last_name' => 'Doe']);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('firstName',   'Jane')
        ->set('lastName',    'Doe')
        ->set('address',     '742 Evergreen Terrace, Springfield')
        ->set('ssnFragment', '6789')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('saved', true)
        // Sensitive form fields should be wiped from component state after save.
        ->assertSet('address',     '')
        ->assertSet('ssnFragment', '');

    $profile = UserProfile::find($user->id);

    // Encrypted columns are populated and decrypt to the original.
    expect($profile->address_encrypted)->not->toBeNull();
    expect(Crypt::decryptString($profile->address_encrypted))
        ->toBe('742 Evergreen Terrace, Springfield');

    expect($profile->ssn_fragment_encrypted)->not->toBeNull();
    expect(Crypt::decryptString($profile->ssn_fragment_encrypted))->toBe('6789');

    // Mask columns are populated and never contain the raw secret.
    expect($profile->ssn_fragment_mask)->toBe('***-**-6789');
    expect($profile->address_mask)->not->toContain('Evergreen');
});

it('member viewing own profile sees masked sensitive fields, admin sees plaintext', function () {
    $encryption = app(EncryptionService::class);

    $member = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $member->addRole(UserRole::MEMBER);
    $bundle = $encryption->encryptWithMask('123 Main St, Anytown', 'address');
    UserProfile::create([
        'user_id'           => $member->id,
        'first_name'        => 'Mem',
        'last_name'         => 'Ber',
        'address_encrypted' => $bundle['encrypted'],
        'address_mask'      => $bundle['mask'],
    ]);

    Livewire::actingAs($member->fresh())
        ->test(Profile::class)
        ->assertSee($bundle['mask'])
        ->assertDontSee('123 Main St');

    $admin = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $admin->addRole(UserRole::ADMIN);
    $adminBundle = $encryption->encryptWithMask('1 Infinite Loop, Cupertino', 'address');
    UserProfile::create([
        'user_id'           => $admin->id,
        'first_name'        => 'Ad',
        'last_name'         => 'Min',
        'address_encrypted' => $adminBundle['encrypted'],
        'address_mask'      => $adminBundle['mask'],
    ]);

    Livewire::actingAs($admin->fresh())
        ->test(Profile::class)
        ->assertSee('1 Infinite Loop, Cupertino');
});
