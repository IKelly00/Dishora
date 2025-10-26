<?php
// debug_kernel.php — run with: php debug_kernel.php
// This boots the full HTTP kernel like Vercel does and prints any exceptions.

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "PHP: " . phpversion() . PHP_EOL;
echo "CWD: " . getcwd() . PHP_EOL . PHP_EOL;

require __DIR__ . '/vendor/autoload.php';

try {
  // load the app
  $app = require_once __DIR__ . '/bootstrap/app.php';
  echo "App loaded: " . (is_object($app) ? get_class($app) : gettype($app)) . PHP_EOL;

  // create the kernel (this triggers provider registration / bootstrapping)
  $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

  // create a fake request for /
  $request = Illuminate\Http\Request::create('/', 'GET');

  try {
    // handle the request (this runs all bootstrap steps)
    $response = $kernel->handle($request);
    echo "Response status: " . $response->getStatusCode() . PHP_EOL;
    echo "Response body (first 1000 chars):" . PHP_EOL;
    echo substr((string)$response->getContent(), 0, 1000) . PHP_EOL;
  } catch (\Throwable $e) {
    echo "----- KERNEL HANDLER EXCEPTION -----" . PHP_EOL;
    echo get_class($e) . ": " . $e->getMessage() . PHP_EOL . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    echo "----- END EXCEPTION -----" . PHP_EOL;
  }

  // terminate kernel
  $kernel->terminate($request, $response ?? new Illuminate\Http\Response('', 500));
} catch (\Throwable $e) {
  echo "----- BOOT ERROR -----" . PHP_EOL;
  echo get_class($e) . ": " . $e->getMessage() . PHP_EOL . PHP_EOL;
  echo $e->getTraceAsString() . PHP_EOL;
  echo "----- END BOOT ERROR -----" . PHP_EOL;
}
