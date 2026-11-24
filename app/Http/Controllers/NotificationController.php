<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()->notifications()->limit(50)->get();

        return Inertia::render('Notifications', [
            'pushPublicKey' => config('webpush.vapid.public_key'),
            'notifications' => $notifications->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                ...$notification->data,
                'read' => $notification->read(),
                'created_at' => $notification->created_at->toIso8601ZuluString(),
            ]),
        ]);
    }

    public function read(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
