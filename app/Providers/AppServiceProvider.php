<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        // Authorize who can view the documentation UI
        \Illuminate\Support\Facades\Gate::define('viewApiDocs', function ($user) {
            // UJI COBA: Tolak semua orang tanpa pandang bulu
            return false;
        });

        // Configure Scramble to use Bearer Token (Sanctum)
        \Dedoc\Scramble\Scramble::configure()
            ->withDocumentTransformers(function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi) {
                $openApi->secure(
                    \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer')
                );
            });
    }
}
