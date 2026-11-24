<?php

use App\Accounts\LockedOut;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectSignedIn;
use App\Scheduling\Refused;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [HandleInertiaRequests::class]);
        $middleware->alias(['role' => EnsureRole::class, 'guest' => RedirectSignedIn::class]);
        // A guest who tried to book comes back to that home.
        $middleware->redirectGuestsTo(fn (Request $request) => route('login', $request->routeIs('homes.book') ? ['home' => $request->route('listing')] : []));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request) => $request->is('api', 'api/*');

        $exceptions->dontReport([Refused::class, LockedOut::class]);
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $isApi($request) || $request->expectsJson());
        $exceptions->render(fn (Refused $refused, Request $request) => $isApi($request)
            ? response()->json(['message' => $refused->getMessage(), 'reason' => $refused->reason], 409)
            : back()->withErrors(['refusal' => $refused->getMessage()]));
        $exceptions->render(fn (LockedOut $lockedOut, Request $request) => $isApi($request)
            ? response()->json(['message' => $lockedOut->getMessage()], 429, ['Retry-After' => $lockedOut->seconds])
            : back()->withErrors(['email' => $lockedOut->getMessage()]));
        // The messages of the framework name classes and routes.
        $exceptions->render(fn (HttpExceptionInterface $exception, Request $request) => $isApi($request)
            ? response()->json(['message' => Response::$statusTexts[$exception->getStatusCode()]], $exception->getStatusCode(), $exception->getHeaders())
            : null);
    })->create();
