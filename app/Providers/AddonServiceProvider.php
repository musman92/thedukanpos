<?php

namespace App\Providers;

use App\Support\AddonCatalog;
use App\Support\AddonRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Discovers addon providers at application boot.
 *
 * Routes must exist before request middleware initializes tenancy, so providers
 * register globally. Every addon route is protected by addon.active:{slug};
 * tenant-specific UI and behavior comes from AddonRegistry.
 */
class AddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AddonRegistry::class);

        foreach (AddonCatalog::all() as $manifest) {
            $provider = $manifest['provider'];
            if ($provider && class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }
}
