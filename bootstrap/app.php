<?php

declare(strict_types=1);

use App\Models\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'appliance.active' => App\Http\Middleware\EnsureApplianceIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle ModelNotFoundException for Command model
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson()) {
                $model = $e->getModel();

                if ($model === Command::class) {
                    return response()->json([
                        'message' => 'Comando non trovato.',
                        'error_code' => 'E002',
                    ], Response::HTTP_NOT_FOUND);
                }
            }

            return null;
        });

        // Handle NotFoundHttpException that wraps ModelNotFoundException for Command
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                $previous = $e->getPrevious();

                if ($previous instanceof ModelNotFoundException && $previous->getModel() === Command::class) {
                    return response()->json([
                        'message' => 'Comando non trovato.',
                        'error_code' => 'E002',
                    ], Response::HTTP_NOT_FOUND);
                }
            }

            return null;
        });
    })->create();
