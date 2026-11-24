<?php

namespace App\Http\Middleware;

use App\Http\AfterSignIn;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The pages to sign in are for who has not. */
class RedirectSignedIn
{
    public function __construct(private readonly AfterSignIn $afterSignIn) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $request->user() === null ? $next($request) : $this->afterSignIn->go($request->user(), $request->query('home'));
    }
}
