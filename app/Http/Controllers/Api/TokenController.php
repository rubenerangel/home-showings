<?php

namespace App\Http\Controllers\Api;

use App\Accounts\CheckCredentials;
use App\Accounts\IssueToken;
use App\Http\AccountInput;
use App\Http\ApiPresenter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TokenController extends Controller
{
    public function store(Request $request, CheckCredentials $checkCredentials, IssueToken $issueToken, ApiPresenter $presenter): JsonResponse
    {
        $data = AccountInput::login($request, AccountInput::DEVICE_NAME);
        $user = $checkCredentials->handle($data['email'], $data['password'], $request->ip());

        return response()->json(['data' => $presenter->token($user, $issueToken->handle($user, $data['device_name']))], 201);
    }

    public function destroyCurrent(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function destroyAll(Request $request): Response
    {
        $request->user()->tokens()->delete();

        return response()->noContent();
    }
}
