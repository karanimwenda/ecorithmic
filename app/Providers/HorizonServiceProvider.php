<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user) {
            // Restrict to users whose email is in the HORIZON_AUTHORIZED_EMAILS
            // comma-separated env variable (e.g. "admin@example.com,ops@example.com").
            // If the env variable is empty, only app.env === 'local' may access it
            // (Horizon's own default guards the dashboard outside local regardless,
            // but we define this gate explicitly per research.md §3).
            $authorizedEmails = array_filter(
                array_map(trim(...), explode(',', (string) config('horizon.authorized_emails', '')))
            );

            if (! empty($authorizedEmails)) {
                return in_array($user?->email, $authorizedEmails, strict: true);
            }

            return app()->environment('local');
        });
    }
}
