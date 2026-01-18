<?php

namespace Thirtybittech\SafeCheck;

use Statamic\Providers\AddonServiceProvider;
use Statamic\Facades\CP\Nav;

class ServiceProvider extends AddonServiceProvider
{
    protected $viewNamespace = 'safe-check';

    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    protected $permissions = [
        'safe-check view',
        'safe-check run',
        'safe-check export',
    ];

    protected $vite = [
        'input' => [
            'resources/js/cp.js',
            'resources/css/cp.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    public function bootAddon()
    {
        $this->extendNavigation();
    }

    protected function extendNavigation()
    {
        Nav::extend(function ($nav) {
            $nav->create('Safe Check')
                ->section('Tools')
                ->icon('lock')
                ->route('safe-check.index')
                ->can('safe-check view');
        });
    }
}

