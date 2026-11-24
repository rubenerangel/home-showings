<?php

namespace App\Http\Controllers\Api;

use App\Accounts\CheckCredentials;
use App\Accounts\IssueToken;
use App\Accounts\RevokeTokens;
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

    public function destroyCurrent(Request $request, RevokeTokens $revokeTokens): Response
    {
        $revokeTokens->current($request->user());

        return response()->noContent();
    }

    public function destroyAll(Request $request, RevokeTokens $revokeTokens): Response
    {
        $revokeTokens->all($request->user());

        return response()->noContent();
    }
}
