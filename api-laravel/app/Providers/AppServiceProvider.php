<?php

namespace App\Providers;

use App\Services\Audit\AuditServiceClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
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

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // Brute-force protection: per email + IP, and a looser cap per IP.
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        // Public form: a short burst limit and a daily cap per IP.
        RateLimiter::for('public-quotes', function (Request $request) {
            return [
                Limit::perMinute(5)->by('minute|'.$request->ip()),
                Limit::perDay(50)->by('day|'.$request->ip()),
            ];
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
