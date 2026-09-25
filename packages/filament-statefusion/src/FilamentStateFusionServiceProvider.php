<?php

namespace A909M\FilamentStateFusion;

use A909M\FilamentStateFusion\Testing\TestsFilamentStateFusion;
use Livewire\Features\SupportTesting\Testable;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentStateFusionServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-statefusion';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->askToStarRepoOnGitHub('a909m/filament-statefusion');
            });
    }

    public function packageRegistered(): void {}

    public function packageBooted(): void
    {
        // Testing
        Testable::mixin(new TestsFilamentStateFusion);
    }

    protected function getAssetPackageName(): ?string
    {
        return 'a909m/filament-statefusion';
    }
}
