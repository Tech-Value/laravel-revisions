<?php

namespace Neurony\Revisions;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Neurony\Revisions\Contracts\RevisionModelContract;
use Neurony\Revisions\Models\Revision;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Create a new service provider instance.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishConfigs();
        $this->publishMigrations();
        $this->registerRouteBindings();
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerBindings();
    }

    /**
     * @return void
     */
    protected function publishConfigs(): void
    {
        $this->publishes([
            __DIR__.'/../config/revisions.php' => config_path('revisions.php'),
        ], 'config');
    }

    /**
     * @return void
     */
    protected function publishMigrations(): void
    {
        if (empty(File::glob(database_path('migrations/*_create_revisions_table.php')))) {
            $timestamp = date('Y_m_d_His', time());
            $migration = database_path("migrations/{$timestamp}_create_revisions_table.php");

            $this->publishes([
                __DIR__.'/../database/migrations/create_revisions_table.php.stub' => $migration,
            ], 'migrations');
        }
    }

    /**
     * @return void
     */
    protected function registerRouteBindings(): void
    {
        Route::model('revision', RevisionModelContract::class);
    }

    /**
     * @return void
     */
    protected function registerBindings(): void
    {
        $this->app->bind(RevisionModelContract::class, $this->config['revisions']['revision_model'] ?? Revision::class);
        $this->app->alias(RevisionModelContract::class, 'revision.model');
    }
}
