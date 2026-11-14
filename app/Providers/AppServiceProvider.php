<?php

namespace App\Providers;

use App\Console\Commands\CreateAdminUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
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
        $this->configureAuthorization();
        $this->configureRateLimiting();
    }

    /**
     * Verificación pública de certificados: 30 consultas por minuto por IP
     * para impedir la enumeración o el abuso de /validar/{hash}.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // Portal del participante (DNI + código, sin contraseña): el código de
        // registro es correlativo y por tanto más adivinable que una clave real,
        // así que limitamos tanto por DNI (frena tantear códigos contra una misma
        // persona) como por IP (frena recorrer muchos DNIs desde un mismo origen).
        RateLimiter::for('portal-login', function (Request $request) {
            $dni = mb_strtoupper(preg_replace('/\s+/', '', (string) $request->input('dni')));

            return [
                Limit::perMinute(5)->by('portal-dni:'.$dni),
                Limit::perMinute(20)->by('portal-ip:'.$request->ip()),
            ];
        });
    }

    /**
     * El Super Admin supera todas las comprobaciones de autorización.
     * Devolver null (y no false) deja que el resto de usuarios pasen por las Policies.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user): ?bool => $user->hasRole(CreateAdminUser::ROLE) ? true : null);
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
