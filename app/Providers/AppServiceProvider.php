<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\Storage;


use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter;

use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    //
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    /* if (config('app.env') === 'production') {
      URL::forceScheme('https');
    } */

    Storage::extend('azure', function ($app, $config) {
      $accountName = $config['accountName'];
      $accountKey = $config['accountKey'];
      $container = $config['container'];

      $endpoint = "DefaultEndpointsProtocol=https;AccountName={$accountName};AccountKey={$accountKey};EndpointSuffix=core.windows.net";
      $client = BlobRestProxy::createBlobService($endpoint);

      $adapter = new AzureBlobStorageAdapter($client, $container);
      $flysystem = new Filesystem($adapter);

      return new FilesystemAdapter($flysystem, $adapter, $config);
    });

    // if ($this->app->environment('production')) {
    //         URL::forceScheme('https');
    // }

    $currentHost = $this->app['request']->getHost();

    if ($currentHost === 'dishora.shop' || str_contains($currentHost, 'dishora.shop')) {

        URL::forceScheme('https');
        $this->app['request']->server->set('HTTPS', 'on');
        
        // Optional: Force the Root URL to ensure links generated are correct
        URL::forceRootUrl('https://dishora.shop');
    } 

    
    // Force the app URL and scheme so signed URLs are generated/validated against APP_URL
    // if (! empty(config('app.url'))) {
    //   // ensure the full root URL (scheme + host + port if any)
    //   URL::forceRootUrl(config('app.url'));

    //   // ensure scheme matches (https if APP_URL is https)
    //   $scheme = parse_url(config('app.url'), PHP_URL_SCHEME);
    //   if (! empty($scheme)) {
    //     URL::forceScheme($scheme);
    //   }
    // }
    }
  }
