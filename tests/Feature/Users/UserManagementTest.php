<?php

use App\Enums\Role;
use App\Exceptions\UserManagementException;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create(['name' => 'Dimas Pratama']);
});

test('owner can list users including inactive ones', function () {
    User::factory()->cashier()->create(['name' => 'Ayu Lestari']);
    User::factory()->cashier()->inactive()->create(['name' => 'Sari Wulandari']);

    $this->actingAs($this->owner)->get(route('owner.users.index'))
        ->assertOk()
        ->assertSeeInOrder(['Dimas Pratama', 'Akun Anda', 'Pemilik', 'Aktif'])
        ->assertSeeInOrder(['Sari Wulandari', 'Kasir', 'Nonaktif', 'Aktifkan']);
});

test('owner can add a user who can then log in', function () {
    $this->actingAs($this->owner)
        ->post(route('owner.users.store'), [
            'name' => 'Rina Kartika',
            'email' => 'rina@demo.test',
            'role' => 'cashier',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])
        ->assertRedirect(route('owner.users.index'))
        ->assertSessionHas('status', 'Pengguna Rina Kartika ditambahkan sebagai Kasir.');

    $user = User::where('email', 'rina@demo.test')->sole();
    expect($user->role)->toBe(Role::Cashier)
        ->and($user->is_active)->toBeTrue()
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue();

    auth()->logout();
    $this->post('/login', ['email' => 'rina@demo.test', 'password' => 'rahasia123'])->assertRedirect('/pos');
});

test('new user input is validated in Indonesian', function () {
    User::factory()->create(['email' => 'ayu@demo.test']);

    $this->actingAs($this->owner)
        ->post(route('owner.users.store'), [
            'name' => '',
            'email' => 'ayu@demo.test',
            'role' => 'admin',
            'password' => 'pendek',
            'password_confirmation' => 'lain',
        ])
        ->assertSessionHasErrors([
            'name' => 'Nama wajib diisi.',
            'email' => 'Email sudah digunakan.',
            'role' => 'Peran yang dipilih tidak valid.',
            'password',
        ]);

    $errors = session('errors')->get('password');
    expect($errors)->toContain('Konfirmasi kata sandi tidak cocok.')
        ->and($errors)->toContain('Kata sandi minimal 8 karakter.');
});

test('owner can change name, email and role of another user', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($this->owner)
        ->put(route('owner.users.update', $cashier), ['name' => 'Budi S.', 'email' => 'budi@demo.test', 'role' => 'owner'])
        ->assertRedirect(route('owner.users.index'));

    expect($cashier->refresh())
        ->name->toBe('Budi S.')
        ->email->toBe('budi@demo.test')
        ->role->toBe(Role::Owner);
});

test('owner cannot demote themselves', function () {
    User::factory()->owner()->create(); // another owner exists, so only the self rule applies

    $this->actingAs($this->owner)
        ->from(route('owner.users.edit', $this->owner))
        ->put(route('owner.users.update', $this->owner), ['name' => 'Dimas', 'email' => $this->owner->email, 'role' => 'cashier'])
        ->assertRedirect(route('owner.users.edit', $this->owner))
        ->assertSessionHasErrors(['role' => 'Anda tidak bisa menurunkan peran akun Anda sendiri.']);

    expect($this->owner->refresh()->role)->toBe(Role::Owner)
        ->and($this->owner->name)->toBe('Dimas Pratama');

    // The edit form does not even offer the choice.
    $this->actingAs($this->owner)->get(route('owner.users.edit', $this->owner))
        ->assertSee('peran akun Anda sendiri tidak bisa diubah')
        ->assertDontSee('<select id="role"', false);
});

test('owner cannot deactivate themselves', function () {
    $this->actingAs($this->owner)
        ->patch(route('owner.users.status', $this->owner), ['is_active' => '0'])
        ->assertRedirect(route('owner.users.index'))
        ->assertSessionHas('error', 'Anda tidak bisa menonaktifkan akun Anda sendiri.');

    expect($this->owner->refresh()->is_active)->toBeTrue();
});

test('the last active owner can be neither demoted nor deactivated', function () {
    $service = app(UserService::class);
    $otherOwner = User::factory()->owner()->create();
    $inactiveOwner = User::factory()->owner()->inactive()->create();

    // Two active owners: one may demote the other.
    $service->update($otherOwner, ['name' => $otherOwner->name, 'email' => $otherOwner->email, 'role' => 'cashier'], $this->owner);
    expect($otherOwner->refresh()->role)->toBe(Role::Cashier);

    // Now $this->owner is the only active owner (the inactive one does not count).
    $actor = User::factory()->cashier()->make(); // stands for any other actor; the service guards regardless of who asks
    expect(fn () => $service->setActive($this->owner, false, $actor))
        ->toThrow(UserManagementException::class, 'Harus ada minimal satu owner aktif.');
    expect(fn () => $service->update($this->owner, ['name' => 'x', 'email' => $this->owner->email, 'role' => 'cashier'], $actor))
        ->toThrow(UserManagementException::class, 'Harus ada minimal satu owner aktif.');

    expect($this->owner->refresh())->role->toBe(Role::Owner)->is_active->toBeTrue()
        ->and($inactiveOwner->refresh()->is_active)->toBeFalse();
});

test('a deactivated user cannot log in and sees why', function () {
    $cashier = User::factory()->cashier()->create(['email' => 'ayu@demo.test']);

    $this->actingAs($this->owner)
        ->patch(route('owner.users.status', $cashier), ['is_active' => '0'])
        ->assertSessionHas('status', "{$cashier->name} dinonaktifkan. Sesi yang sedang aktif akan keluar otomatis.");
    auth()->logout();

    $this->post('/login', ['email' => 'ayu@demo.test', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Akun ini sudah dinonaktifkan. Hubungi pemilik kedai untuk mengaktifkannya kembali.']);
    $this->assertGuest();

    // A wrong password does not reveal that the account exists but is inactive.
    $this->post('/login', ['email' => 'ayu@demo.test', 'password' => 'salah'])
        ->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
});

test('a deactivated user who is still logged in is signed out on the next request', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)->get('/pos')->assertOk();

    $cashier->forceFill(['is_active' => false])->save(); // e.g. the owner deactivates them meanwhile

    $this->get('/pos')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Akun ini sudah dinonaktifkan. Hubungi pemilik kedai untuk mengaktifkannya kembali.']);
    $this->assertGuest();

    // Livewire requests from the open POS screen are stopped the same way (Livewire follows the redirect).
    $this->actingAs($cashier->refresh())
        ->post(route('default-livewire.update'), [], ['X-Livewire' => 'true'])
        ->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a reactivated user can log in again', function () {
    $cashier = User::factory()->cashier()->inactive()->create(['email' => 'sari@demo.test']);

    $this->actingAs($this->owner)
        ->patch(route('owner.users.status', $cashier), ['is_active' => '1'])
        ->assertSessionHas('status', "{$cashier->name} diaktifkan kembali dan bisa masuk lagi.");
    auth()->logout();

    $this->post('/login', ['email' => 'sari@demo.test', 'password' => 'password'])->assertRedirect('/pos');
});

test('owner can reset a password; the old one stops working and remember-me is revoked', function () {
    $cashier = User::factory()->cashier()->create(['email' => 'budi@demo.test', 'remember_token' => 'old-token']);

    $this->actingAs($this->owner)
        ->put(route('owner.users.password.update', $cashier), ['password' => 'kopinusa2026', 'password_confirmation' => 'kopinusa2026'])
        ->assertRedirect(route('owner.users.index'))
        ->assertSessionHas('status', "Kata sandi {$cashier->name} sudah diganti. Beri tahu kata sandi baru secara langsung.");

    expect($cashier->refresh()->remember_token)->not->toBe('old-token');
    auth()->logout();

    $this->post('/login', ['email' => 'budi@demo.test', 'password' => 'password'])->assertSessionHasErrors('email');
    $this->post('/login', ['email' => 'budi@demo.test', 'password' => 'kopinusa2026'])->assertRedirect('/pos');
});

test('password reset is validated', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($this->owner)
        ->put(route('owner.users.password.update', $cashier), ['password' => 'abc', 'password_confirmation' => 'abc'])
        ->assertSessionHasErrors(['password' => 'Kata sandi minimal 8 karakter.']);
});

test('users cannot be deleted', function () {
    expect(Route::has('owner.users.destroy'))->toBeFalse();

    $this->actingAs($this->owner)->delete('/owner/users/'.$this->owner->id)->assertStatus(405);
});

test('deactivating asks for confirmation first', function () {
    $cashier = User::factory()->cashier()->create(['name' => 'Ayu Lestari']);

    $this->actingAs($this->owner)->get(route('owner.users.index'))
        ->assertSee('role="alertdialog"', false)
        ->assertSee('Nonaktifkan Ayu Lestari?');
});
