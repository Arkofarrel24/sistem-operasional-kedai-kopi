<?php

namespace App\Http\Controllers\Owner;

use App\Exceptions\UserManagementException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserStatusController extends Controller
{
    /**
     * Activate or deactivate a user (users are never deleted).
     */
    public function update(Request $request, User $user, UserService $users): RedirectResponse
    {
        $active = $request->validate(['is_active' => ['required', 'boolean']])['is_active'];

        try {
            $users->setActive($user, (bool) $active, $request->user());
        } catch (UserManagementException $exception) {
            return to_route('owner.users.index')->with('error', $exception->getMessage());
        }

        return to_route('owner.users.index')->with('status', $active
            ? "{$user->name} diaktifkan kembali dan bisa masuk lagi."
            : "{$user->name} dinonaktifkan. Sesi yang sedang aktif akan keluar otomatis.");
    }
}
