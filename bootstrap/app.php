<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'portal.context' => \App\Http\Middleware\EnsurePortalContext::class,
        ]);
        $middleware->redirectGuestsTo(function ($request) {
            return $request->is('admin') || $request->is('admin/*')
                ? '/admin/login'
                : '/portal/login';
        });
        $middleware->redirectUsersTo(function ($request) {
            return $request->is('admin') || $request->is('admin/*')
                ? '/admin'
                : '/portal';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, \Illuminate\Http\Request $request) {
            if ($response->getStatusCode() !== 419 || ! $request->is('portal', 'portal/*')) {
                return $response;
            }

            $to = $request->user() ? '/portal' : '/portal/login';

            return redirect()->to($to)->with('status', 'That page expired. Please try again.');
        });
    })->create();
