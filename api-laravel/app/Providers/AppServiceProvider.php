<?php

namespace App\Providers;

use App\Services\Audit\AuditServiceClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * App-wide setup that runs on every request.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Tell Laravel how to build the AuditServiceClient (values come from config/services.php,
     * which reads them from .env). "singleton" = build it once and reuse it.
     */
    public function register(): void
    {
        $this->app->singleton(AuditServiceClient::class, fn () => new AuditServiceClient(
            rtrim((string) config('services.audit.url'), '/'),
            config('services.audit.secret'),
            config('services.audit.read_key'),
            (int) config('services.audit.timeout'),
        ));
    }

    public function boot(): void
    {
        // Return single resources as { ...fields } instead of { "data": { ...fields } }.
        JsonResource::withoutWrapping();

        $this->configureRateLimiting();
    }

    /**
     * Rate limits = "how many requests may one person send per minute".
     * The routes use them by name, e.g. ->middleware('throttle:login').
     * Going over the limit returns 429 Too Many Requests.
     */
    private function configureRateLimiting(): void
    {
        // Login: stops password guessing. 5 tries per minute for one email from one IP,
        // and 20 per minute per IP in total (so one IP can't try many emails).
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        // Public quote form: 5 per minute and 50 per day for each IP address (stops spam floods).
        RateLimiter::for('public-quotes', function (Request $request) {
            return [
                Limit::perMinute(5)->by('minute|'.$request->ip()),
                Limit::perDay(50)->by('day|'.$request->ip()),
            ];
        });

        // Logged-in staff: 120 requests per minute per user. Plenty for normal use.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
