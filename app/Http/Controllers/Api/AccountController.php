<?php

namespace App\Http\Controllers\Api;

use App\Accounts\IssueToken;
use App\Accounts\RegisterUser;
use App\Http\AccountInput;
use App\Http\ApiPresenter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function __construct(private readonly ApiPresenter $presenter) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->presenter->user($request->user())]);
    }

    public function store(Request $request, RegisterUser $registerUser, IssueToken $issueToken): JsonResponse
    {
        $data = AccountInput::registration($request, AccountInput::DEVICE_NAME);

        // An account without its token would only leave a taken email behind.
        [$user, $token] = DB::transaction(function () use ($data, $registerUser, $issueToken) {
            $user = $registerUser->handle($data);

            return [$user, $issueToken->handle($user, $data['device_name'])];
        });

        return response()->json(['data' => $this->presenter->token($user, $token)], 201);
    }
}
