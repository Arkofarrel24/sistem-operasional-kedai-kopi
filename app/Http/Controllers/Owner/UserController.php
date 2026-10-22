<?php

namespace App\Http\Controllers\Owner;

use App\Enums\Role;
use App\Exceptions\UserManagementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\UserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->orderByDesc('is_active')
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(20);

        return view('owner.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('owner.users.create', ['user' => new User(['role' => Role::Cashier]), 'roles' => Role::cases()]);
    }

    public function store(UserRequest $request, UserService $users): RedirectResponse
    {
        $user = $users->create($request->validated());

        return to_route('owner.users.index')->with('status', "Pengguna {$user->name} ditambahkan sebagai {$user->role->label()}.");
    }

    public function edit(User $user): View
    {
        return view('owner.users.edit', ['user' => $user, 'roles' => Role::cases()]);
    }

    public function update(UserRequest $request, User $user, UserService $users): RedirectResponse
    {
        try {
            $users->update($user, $request->validated(), $request->user());
        } catch (UserManagementException $exception) {
            return back()->withInput()->withErrors(['role' => $exception->getMessage()]);
        }

        return to_route('owner.users.index')->with('status', "Data {$user->name} diperbarui.");
    }
}
