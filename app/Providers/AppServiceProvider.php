<?php

namespace App\Providers;

use App\Services\DemoLlmService;
use App\Services\LlmService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Modo demo: reemplaza el LlmService real por un mock determinista
        // que devuelve borradores prefabricados al instante. Útil para
        // grabar videos sin depender del rate-limit de OpenRouter.
        // Activar con DEMO_LLM_MOCK=true en .env (NO usar en producción).
        if (env('DEMO_LLM_MOCK') === true || env('DEMO_LLM_MOCK') === 'true') {
            $this->app->singleton(LlmService::class, DemoLlmService::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Register Socialite Microsoft provider from socialiteproviders/microsoft
        Event::listen(
            SocialiteWasCalled::class,
            MicrosoftExtendSocialite::class.'@handle',
        );
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        if (str_starts_with(config('app.url', ''), 'https://')) {
            URL::forceScheme('https');
        }

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
