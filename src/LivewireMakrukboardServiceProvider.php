<?php

namespace Veerasaroot\LivewireMakrukboard;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class LivewireMakrukboardServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Register the config file
        $this->publishes([
            __DIR__.'/../config/makrukboard.php' => config_path('makrukboard.php'),
        ], 'makrukboard-config');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'livewire-makrukboard');
        
        // Publish views
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/livewire-makrukboard'),
        ], 'makrukboard-views');

        // Register Livewire component
        Livewire::component('makruk-board', MakrukBoard::class);
    }

    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/makrukboard.php', 'makrukboard'
        );
    }
}