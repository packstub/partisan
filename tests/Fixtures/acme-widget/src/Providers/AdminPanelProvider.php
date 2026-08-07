<?php

namespace Acme\Widget\Providers;

use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Requires the config service during register(), like real package
     * providers do — guards partisan against registering providers before
     * the application is bootstrapped enough for mergeConfigFrom().
     */
    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../../config/widget.php', 'widget');
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'Acme\\Widget\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'Acme\\Widget\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'Acme\\Widget\\Filament\\Widgets');
    }
}
