<?php

namespace App\Http\Controllers;

use App\Push\BrowserSubscription;
use App\Push\PushService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use NotificationChannels\WebPush\PushSubscription;

class PushSubscriptionController extends Controller
{
    public function store(Request $request, BrowserSubscription $browserSubscription): Response
    {
        $data = $request->validate([
            'endpoint' => ['bail', 'required', 'string', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH,
                function (string $attribute, string $value, Closure $fail) {
                    if (! PushService::accepts($value)) {
                        $fail('This push service is not supported.');
                    }
                },
            ],
            // A P-256 point of 65 bytes and a secret of 16, in base64url.
            'keys.p256dh' => ['bail', 'required', 'string', 'regex:/^[A-Za-z0-9_-]{87}=?$/D'],
            'keys.auth' => ['bail', 'required', 'string', 'regex:/^[A-Za-z0-9_-]{22}={0,2}$/D'],
        ]);

        $browserSubscription->add($request->user(), $request->session(), $data);

        return response()->noContent();
    }

    public function destroy(Request $request, BrowserSubscription $browserSubscription): Response
    {
        $data = $request->validate(['endpoint' => ['required', 'string']]);

        $browserSubscription->remove($request->user(), $data['endpoint']);

        return response()->noContent();
    }
}
