<?php

namespace Thirtybittech\SafeCheck;

use Statamic\Providers\AddonServiceProvider;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;


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
        $this->registerPermissions();
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

   protected function registerPermissions(): void
    {
        Permission::extend(function () {
            // handle, label
            Permission::group('safe-check', 'Safe Check', function () {
                Permission::register('safe-check view')->label('View results');
                Permission::register('safe-check run')->label('Run scan');
                Permission::register('safe-check export')->label('Export report');
            });
        });
    }
}

