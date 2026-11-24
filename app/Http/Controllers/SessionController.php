<?php

namespace App\Http\Controllers;

use App\Accounts\Credentials;
use App\Push\BrowserSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    private const MAX_FAILURES = 5;

    public function __construct(private readonly BrowserSubscription $browserSubscription) {}

    public function create(): Response
    {
        return Inertia::render('Login');
    }

    public function store(Request $request): RedirectResponse
    {
        Credentials::lowerEmail($request);
        $credentials = $request->validate([
            'email' => Credentials::EMAIL_RULES,
            'password' => ['bail', 'required', 'string'],
        ]);

        // Counted before checking, so attempts sent at once do not get past the limit.
        $key = 'login:'.hash('sha256', $credentials['email'].'|'.$request->ip());
        if (RateLimiter::hit($key) > self::MAX_FAILURES) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in a minute.']);
        }

        $previous = $request->user();
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'The email or the password is not right.']);
        }
        RateLimiter::clear($key);

        $this->browserSubscription->forget($previous, $request->session());
        $request->session()->regenerate();

        return redirect()->route($request->user()->role->home());
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
