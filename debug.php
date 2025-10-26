<?php
// debug.php — run from project root with `php debug.php`
// This version ensures Composer autoload is loaded before requiring bootstrap/app.php.

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "PHP: " . phpversion() . PHP_EOL;
echo "CWD: " . getcwd() . PHP_EOL . PHP_EOL;

// Show services.php
echo "=== services.php ===" . PHP_EOL;
if (file_exists(__DIR__ . '/bootstrap/cache/services.php')) {
  $content = file_get_contents(__DIR__ . '/bootstrap/cache/services.php');
  echo substr($content, 0, 4000) . (strlen($content) > 4000 ? PHP_EOL . '... (truncated) ...' . PHP_EOL : PHP_EOL);
} else {
  echo "services.php missing" . PHP_EOL;
}

// Show packages.php
echo PHP_EOL . "=== packages.php ===" . PHP_EOL;
if (file_exists(__DIR__ . '/bootstrap/cache/packages.php')) {
  echo substr(file_get_contents(__DIR__ . '/bootstrap/cache/packages.php'), 0, 4000) . PHP_EOL;
} else {
  echo "packages.php missing" . PHP_EOL;
}

// Providers list
echo PHP_EOL . "=== providers list (from services.php) ===" . PHP_EOL;
if (file_exists(__DIR__ . '/bootstrap/cache/services.php')) {
  $arr = include __DIR__ . '/bootstrap/cache/services.php';
  if (!is_array($arr)) {
    echo "services.php did not return an array (type=" . gettype($arr) . ")" . PHP_EOL;
  } else {
    $providers = $arr['providers'] ?? null;
    if (!is_array($providers)) {
      echo "providers not found or not an array in services.php" . PHP_EOL;
    } else {
      echo "providers count: " . count($providers) . PHP_EOL;
      $i = 0;
      foreach ($providers as $p) {
        echo " - $p" . PHP_EOL;
        $i++;
        if ($i >= 200) {
          echo "... (truncated providers list) ..." . PHP_EOL;
          break;
        }
      }
      $hasView = false;
      foreach ($providers as $p) {
        if (stripos($p, 'ViewServiceProvider') !== false || stripos($p, 'Illuminate\\View') !== false) {
          $hasView = true;
          break;
        }
      }
      echo "Contains ViewServiceProvider? " . ($hasView ? 'YES' : 'NO') . PHP_EOL;
    }
  }
} else {
  echo "services.php missing; cannot list providers" . PHP_EOL;
}

echo PHP_EOL . "=== Attempt to bootstrap app and resolve 'view' ===" . PHP_EOL;

try {
  // LOAD AUTLOADER (this is what public/index.php does on Vercel)
  require __DIR__ . '/vendor/autoload.php';
  // now require bootstrap/app.php (same as production)
  $app = require __DIR__ . '/bootstrap/app.php';
  echo "App bootstrapped: " . (is_object($app) ? get_class($app) : gettype($app)) . PHP_EOL;

  try {
    $view = $app->make('view');
    echo "MAKE_VIEW: succeeded — " . (is_object($view) ? get_class($view) : gettype($view)) . PHP_EOL;
  } catch (\Throwable $e) {
    echo "MAKE_VIEW_ERROR: " . get_class($e) . ": " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
  }
} catch (\Throwable $e) {
  echo "BOOT_ERROR: " . get_class($e) . ": " . $e->getMessage() . PHP_EOL;
  echo $e->getTraceAsString() . PHP_EOL;
}

echo PHP_EOL . "=== End debug ===" . PHP_EOL;
