<?php

use App\Exceptions\BusinessRuleException;
use App\Exceptions\IdempotencyConflictException;
use App\Http\Middleware\AddRequestContext;
use App\Http\Middleware\EnsureActiveBranch;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AddRequestContext::class);
        $middleware->alias(['active.branch' => EnsureActiveBranch::class]);
        $middleware->redirectUsersTo(fn () => route('occupancy.index'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Rejected operations are expected outcomes: show the message on the form instead of reporting an error.
        $exceptions->dontReport([BusinessRuleException::class, IdempotencyConflictException::class]);
        $exceptions->render(fn (BusinessRuleException|IdempotencyConflictException $exception, Request $request) => $request->expectsJson()
            ? response()->json(['message' => $exception->getMessage()], 422)
            : back()->withInput()->withErrors(['operation' => $exception->getMessage()]));
    })->create();
