<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\UserService;

// Real concurrency: see runConcurrently() in tests/Pest.php.

test('two owners demoting each other at the same time leave one active owner', function () {
    $a = User::factory()->owner()->create(['name' => 'Owner A']);
    $b = User::factory()->owner()->create(['name' => 'Owner B']);
    $demote = fn (User $target, User $actor) => fn () => app(UserService::class)->update(
        User::find($target->id),
        ['name' => $target->name, 'email' => $target->email, 'role' => 'cashier'],
        User::find($actor->id),
    )->name;

    $outcomes = runConcurrently(
        [$demote($b, $a), $demote($a, $b)],
        fn ($connection) => $connection->table('users')->whereIn('id', [$a->id, $b->id])->orderBy('id')->lockForUpdate()->get(),
    );

    expect(array_filter($outcomes, fn ($o) => str_starts_with($o, 'ok:')))->toHaveCount(1, implode(' | ', $outcomes))
        ->and(array_filter($outcomes, fn ($o) => str_starts_with($o, 'rejected:Harus ada minimal satu owner aktif.')))->toHaveCount(1)
        ->and(User::where('role', Role::Owner)->where('is_active', true)->count())->toBe(1);
});
