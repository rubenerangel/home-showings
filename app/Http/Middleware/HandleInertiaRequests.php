<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'user' => fn () => $request->user()?->only('id', 'name', 'role'),
            'unreadNotifications' => fn () => $request->user()?->unreadNotifications()->count(),
        ];
    }
}
