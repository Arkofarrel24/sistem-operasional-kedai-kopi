<?php

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\UserManagementException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Owner-side user management (PLAN.md rules 15 and 16):
 * - users are never deleted, only deactivated (sales and stock movements refer to them),
 * - an owner cannot deactivate or demote themselves,
 * - there is always at least one active owner. The active owner rows are locked while checking,
 *   so two owners demoting each other at the same moment cannot both succeed.
 */
class UserService
{
    /**
     * @param  array{name: string, email: string, role: string, password: string}  $attributes
     */
    public function create(array $attributes): User
    {
        return User::create($attributes + ['is_active' => true]);
    }

    /**
     * @param  array{name: string, email: string, role: string}  $attributes
     */
    public function update(User $user, array $attributes, User $actor): User
    {
        $role = Role::from($attributes['role']);

        return DB::transaction(function () use ($user, $attributes, $role, $actor) {
            if ($user->role === Role::Owner && $role !== Role::Owner) {
                if ($user->is($actor)) {
                    throw new UserManagementException('Anda tidak bisa menurunkan peran akun Anda sendiri.');
                }

                $this->ensureAnotherActiveOwner($user);
            }

            $user->update(['name' => $attributes['name'], 'email' => $attributes['email'], 'role' => $role]);

            return $user;
        });
    }

    public function setActive(User $user, bool $active, User $actor): User
    {
        return DB::transaction(function () use ($user, $active, $actor) {
            if (! $active) {
                if ($user->is($actor)) {
                    throw new UserManagementException('Anda tidak bisa menonaktifkan akun Anda sendiri.');
                }

                if ($user->role === Role::Owner) {
                    $this->ensureAnotherActiveOwner($user);
                }
            }

            $user->forceFill(['is_active' => $active])->save();

            return $user;
        });
    }

    /**
     * Set a new password chosen by the owner and sign the user out of "remember me" devices.
     */
    public function resetPassword(User $user, string $password): User
    {
        $user->forceFill(['password' => $password])->setRememberToken(null);
        $user->save();

        return $user;
    }

    /**
     * Lock every active owner row (in id order, target included) before counting, so a concurrent
     * demotion waits here and then sees this one's result instead of deadlocking.
     */
    private function ensureAnotherActiveOwner(User $user): void
    {
        $activeOwners = User::query()
            ->where('role', Role::Owner)
            ->where('is_active', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');

        if ($activeOwners->reject(fn (int $id) => $id === $user->getKey())->isEmpty()) {
            throw new UserManagementException('Harus ada minimal satu owner aktif. Tambahkan atau aktifkan owner lain terlebih dulu.');
        }
    }
}
