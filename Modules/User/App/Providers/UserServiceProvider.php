<?php

declare(strict_types=1);

namespace Modules\User\App\Providers;

use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\User\App\Notifications\WelcomeNotification;

class UserServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(Verified::class, function (Verified $event): void {
            $event->user->notify(new WelcomeNotification());
        });

        $this->loadMigrationsFrom(module_path('User', 'Database/migrations'));
        $this->loadRoutesFrom(module_path('User', 'routes/web.php'));
        $this->loadViewsFrom(module_path('User', 'resources/views'), 'user');
        $this->loadTranslationsFrom(module_path('User', 'lang'), 'user');
    }

    public function register(): void {}
}
