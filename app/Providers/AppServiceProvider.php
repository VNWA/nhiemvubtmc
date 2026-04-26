<?php

namespace App\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Display timezone for all human-readable datetimes sent to the frontend.
     * Storage stays in UTC (config/app.php), but anything we render goes
     * through Carbon::formatVn() so the output is identical for every viewer
     * regardless of their browser/VPN timezone.
     */
    public const DISPLAY_TIMEZONE = 'Asia/Ho_Chi_Minh';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerDateMacros();
        date_default_timezone_set(config('app.timezone'));
        // Note: UpdateUserOnLogin is auto-registered by Laravel via the
        // Login type-hint on its handle() method — do NOT re-register it
        // here, otherwise the user.login activity log fires twice.
    }

    /**
     * Register Carbon macros used to render datetimes in Vietnam timezone.
     */
    protected function registerDateMacros(): void
    {
        $macro = function (string $format = 'd/m/Y H:i:s'): string {
            /** @var CarbonInterface $this */
            return $this->copy()
                ->setTimezone(AppServiceProvider::DISPLAY_TIMEZONE)
                ->format($format);
        };

        Carbon::macro('formatVn', $macro);
        CarbonImmutable::macro('formatVn', $macro);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
