<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiPresenter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    public function index(Request $request, ApiPresenter $presenter): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->latestNotifications()->map($presenter->notification(...)),
            'unread' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request): Response
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }
}
