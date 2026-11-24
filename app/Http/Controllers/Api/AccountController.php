<?php

namespace App\Http\Controllers\Api;

use App\Accounts\RegisterDevice;
use App\Http\AccountInput;
use App\Http\ApiPresenter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(private readonly ApiPresenter $presenter) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->presenter->user($request->user())]);
    }

    public function store(Request $request, RegisterDevice $registerDevice): JsonResponse
    {
        $data = AccountInput::registration($request, AccountInput::DEVICE_NAME);
        [$user, $token] = $registerDevice->handle($data, $data['device_name']);

        return response()->json(['data' => $this->presenter->token($user, $token)], 201);
    }
}
