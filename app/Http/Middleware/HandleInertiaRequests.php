<?php

namespace App\Http\Middleware;

use App\Notifications\Inbox;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(private readonly Inbox $inbox) {}

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'user' => fn () => $request->user()?->only('id', 'name', 'role'),
            'unreadNotifications' => fn () => $request->user() === null ? null : $this->inbox->unread($request->user()),
        ];
    }
}
