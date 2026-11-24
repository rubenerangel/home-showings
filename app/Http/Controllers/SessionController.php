<?php

namespace App\Http\Controllers;

use App\Accounts\CheckCredentials;
use App\Http\AccountInput;
use App\Push\BrowserSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function __construct(private readonly BrowserSubscription $browserSubscription) {}

    public function create(): Response
    {
        return Inertia::render('Login');
    }

    public function store(Request $request, CheckCredentials $checkCredentials): RedirectResponse
    {
        $credentials = AccountInput::login($request);
        $previous = $request->user();
        $user = $checkCredentials->handle($credentials['email'], $credentials['password'], $request->ip());

        Auth::login($user);
        $this->browserSubscription->forget($previous, $request->session());
        $request->session()->regenerate();

        return redirect()->route($user->role->home());
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->browserSubscription->forget($request->user(), $request->session());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
