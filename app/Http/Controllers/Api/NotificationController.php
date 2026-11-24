<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiPresenter;
use App\Http\Controllers\Controller;
use App\Notifications\Inbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    public function index(Request $request, Inbox $inbox, ApiPresenter $presenter): JsonResponse
    {
        return response()->json([
            'data' => $inbox->latest($request->user())->map($presenter->notification(...)),
            'unread' => $inbox->unread($request->user()),
        ]);
    }

    public function read(Request $request, Inbox $inbox): Response
    {
        $inbox->markAllAsRead($request->user());

        return response()->noContent();
    }
}
