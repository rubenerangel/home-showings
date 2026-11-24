<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Push\BrowserSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function create(): Response
    {
        $users = User::query()->orderBy('name')->get(['id', 'name', 'role']);

        return Inertia::render('Picker', [
            'sellers' => $users->where('role', Role::Seller)->values(),
            'buyers' => $users->where('role', Role::Buyer)->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $user = User::findOrFail($data['user_id']);

        BrowserSubscription::forget($request->user(), $request->session());
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($user->role === Role::Seller ? 'seller.home' : 'buyer.home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        BrowserSubscription::forget($request->user(), $request->session());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('picker');
    }
}
