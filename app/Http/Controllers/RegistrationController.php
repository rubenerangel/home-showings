<?php

namespace App\Http\Controllers;

use App\Accounts\RegisterUser;
use App\Http\AccountInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Register');
    }

    public function store(Request $request, RegisterUser $registerUser): RedirectResponse
    {
        $user = $registerUser->handle(AccountInput::registration($request));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($user->role->home());
    }
}
