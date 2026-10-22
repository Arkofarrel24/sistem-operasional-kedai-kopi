<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ResetPasswordRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserPasswordController extends Controller
{
    public function edit(User $user): View
    {
        return view('owner.users.password', compact('user'));
    }

    public function update(ResetPasswordRequest $request, User $user, UserService $users): RedirectResponse
    {
        $users->resetPassword($user, $request->validated('password'));

        return to_route('owner.users.index')->with('status', "Kata sandi {$user->name} sudah diganti. Beri tahu kata sandi baru secara langsung.");
    }
}
