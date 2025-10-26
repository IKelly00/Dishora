<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    web: __DIR__ . '/../routes/web.php',
    commands: __DIR__ . '/../routes/console.php',
    health: '/up',
  )
  ->withMiddleware(function (Middleware $middleware) {
    $middleware->group('web', [
      \Illuminate\Cookie\Middleware\EncryptCookies::class,
      \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
      \Illuminate\Session\Middleware\StartSession::class,
      \Illuminate\View\Middleware\ShareErrorsFromSession::class,

      // Prevent users to access dashboard without loging in
      \App\Http\Middleware\PreventBackHistory::class,
    ]);

    $middleware->alias([
      'verified.business' => \App\Http\Middleware\EnsureBusinessIsVerified::class,
      'role' => \App\Http\Middleware\EnsureUserHasRole::class,
      'is.superadmin' => \App\Http\Middleware\IsSuperAdmin::class,
    ]);
  })
  ->withExceptions(function (Exceptions $exceptions) {
    // If email token expired, will be redirected to 403
    $exceptions->render(function (\Illuminate\Routing\Exceptions\InvalidSignatureException $e) {
      abort(403, 'Verification link has expired or is invalid');
    });
  })
  ->create();
