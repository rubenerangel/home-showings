<?php

namespace App\Http\Controllers;

use App\Accounts\Credentials;
use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Register');
    }

    public function store(Request $request): RedirectResponse
    {
        Credentials::lowerEmail($request);
        $data = $request->validate([
            'name' => ['bail', 'required', 'string', 'max:255', 'regex:/^[^\x00-\x1F\x7F]+$/u'],
            'email' => [...Credentials::EMAIL_RULES, 'unique:users'],
            'password' => ['bail', 'required', 'string', 'confirmed', 'min:8', self::hashable(...)],
            'role' => ['bail', 'required', 'string', Rule::enum(Role::class)],
        ]);

        try {
            $user = User::create($data);
        } catch (UniqueConstraintViolationException) {
            // Someone else registered it after the validation.
            throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => 'email'])]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($user->role->home());
    }

    /** Bcrypt ignores what comes after 72 bytes, and rejects null bytes. */
    private static function hashable(string $attribute, string $value, Closure $fail): void
    {
        if (strlen($value) > 72) {
            $fail('The password must not be longer than 72 bytes.');
        } elseif (str_contains($value, "\0")) {
            $fail('The password has a character that is not allowed.');
        }
    }
}
