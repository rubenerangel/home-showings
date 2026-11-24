<?php

namespace App\Http\Controllers;

use App\Accounts\RegisterUser;
use App\Http\AccountInput;
use App\Http\AfterSignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function __construct(private readonly AfterSignIn $afterSignIn) {}

    public function create(Request $request): Response
    {
        return Inertia::render('Register', ['home' => $this->afterSignIn->wanted($request->query('home'))]);
    }

    public function store(Request $request, RegisterUser $registerUser): RedirectResponse
    {
        $user = $registerUser->handle(AccountInput::registration($request));
        Auth::login($user);
        $request->session()->regenerate();

        return $this->afterSignIn->go($user, $request->input('home'));
    }
}
