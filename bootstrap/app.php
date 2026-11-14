<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Custom 404 / Page Not Found handling for web requests
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->wantsJson()) {
                return response()->view('errors.404', [
                    'exception' => $e,
                    'statusCode' => 404,
                ], 404);
            }
        });

        // Custom unhandled exception handling for web requests
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return null;
            }

            if (! config('app.debug') || env('FORCE_ERROR_PAGE', false) || $request->has('preview_error')) {
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                $view = view()->exists("errors.{$statusCode}") ? "errors.{$statusCode}" : 'errors.500';

                return response()->view($view, [
                    'exception' => $e,
                    'statusCode' => $statusCode,
                ], $statusCode);
            }
        });
    })->create();
