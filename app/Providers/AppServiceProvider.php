<?php

declare(strict_types=1);

namespace App\Providers;

use Laravel\Cashier\Cashier;
use Modules\User\App\Models\User;

use App\Mail\MicrosoftGraphTransport;

use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Apple\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Cashier::useCustomerModel(User::class);

        Mail::extend('graph', function (array $config = []) {
            return new MicrosoftGraphTransport(
                tenantId: (string) config('services.microsoft_graph.tenant_id'),
                clientId: (string) config('services.microsoft_graph.client_id'),
                clientSecret: (string) config('services.microsoft_graph.client_secret'),
                sender: (string) config('services.microsoft_graph.sender'),
            );
        });

        Gate::before(function ($user): ?bool {
            if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
                return true;
            }

            return null;
        });

        View::addNamespace('app', resource_path('views'));

        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('apple', Provider::class);
        });

        $availableLocales = config('app.available_locales', ['en']);
        $localeLabels = [
            'en' => 'English',
            'tr' => 'Turkish',
        ];

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) use ($availableLocales, $localeLabels): void {
            $switch
                ->locales($availableLocales)
                ->labels(collect($availableLocales)->mapWithKeys(
                    fn (string $locale) => [$locale => $localeLabels[$locale] ?? strtoupper($locale)]
                )->all())
                ->visible(insidePanels: count($availableLocales) > 1, outsidePanels: false);
        });
    }
}
