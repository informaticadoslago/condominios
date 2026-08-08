<?php
namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use App\Models\EstadoUsuario;
use Illuminate\Support\Facades\Hash;
use App\Actions\Fortify\CreateNewUser;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use Illuminate\Support\Facades\RateLimiter;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Illuminate\Validation\ValidationException;

class FortifyServiceProvider extends ServiceProvider
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
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::authenticateUsing(function (Request $request) {
            $email = $request->email;
            $user  = User::where('estado_id', EstadoUsuario::USUARIO_ACTIVO)
                ->where(function ($query) use ($email) {
                    $query->where('email', $email)
                        ->orWhere('login', $email);
                })->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return null;
            }

            // Activo no basta: mientras no pinche el enlace del correo de alta
            // (email_verified_at) no puede entrar. Aviso específico, distinto del
            // genérico de credenciales incorrectas.
            if (! $user->email_verified_at) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('Antes de entrar por favor confirma tu correo electrónico. Habrás recibido un correo-e. Si no lo encuentras no te olvides de buscar en la carpeta de correo no deseado.'),
                ]);
            }

            return $user;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
