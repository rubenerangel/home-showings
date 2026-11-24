<?php

namespace App\Http\Controllers;

use App\Http\Presenter;
use App\Notifications\Inbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request, Inbox $inbox, Presenter $presenter): Response
    {
        return Inertia::render('Notifications', [
            'pushPublicKey' => config('webpush.vapid.public_key'),
            'notifications' => $inbox->latest($request->user())->map($presenter->notification(...)),
        ]);
    }

    public function read(Request $request, Inbox $inbox): RedirectResponse
    {
        $inbox->markAllAsRead($request->user());

        return back();
    }
}
