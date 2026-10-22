<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

test('login is rate limited after five failed attempts, with an Indonesian message', function () {
    $user = User::factory()->create(['email' => 'ayu@demo.test']);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => 'ayu@demo.test', 'password' => 'salah'])
            ->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
    }

    // Even the right password is refused while locked out.
    $this->post('/login', ['email' => 'ayu@demo.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(session('errors')->first('email'))->toMatch('/^Terlalu banyak percobaan masuk\. Coba lagi dalam \d+ detik\.$/');

    RateLimiter::clear('ayu@demo.test|127.0.0.1');
});
