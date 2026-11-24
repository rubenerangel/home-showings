<?php

namespace App\Http\Controllers;

use App\Http\Presenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request, Presenter $presenter): Response
    {
        return Inertia::render('Notifications', [
            'pushPublicKey' => config('webpush.vapid.public_key'),
            'notifications' => $request->user()->latestNotifications()->map($presenter->notification(...)),
        ]);
    }

    public function read(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
