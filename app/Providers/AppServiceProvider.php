<?php
namespace App\Providers;

use Illuminate\Auth\Events\Logout;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Lab404\Impersonate\Events\LeaveImpersonation;
use Lab404\Impersonate\Events\TakeImpersonation;
use Lab404\Impersonate\Services\ImpersonateManager;
use Symfony\Component\Mime\Address;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Va en register() y no en boot() a propósito: el paquete escucha Logout desde su
        // boot() para limpiar la suplantación de la sesión, y los listeners corren en orden
        // de registro. Desde boot() llegaríamos tarde y sin datos que registrar.
        Event::listen(Logout::class, function (Logout $evento) {
            $manager = app(ImpersonateManager::class);

            if (! $manager->isImpersonating()) {
                return;
            }

            $impersonador = $manager->findUserById(
                $manager->getImpersonatorId(),
                $manager->getImpersonatorGuardName()
            );

            $this->registrarEnElLog('Suplantación terminada (cierre de sesión)', $impersonador, $evento->user);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            return $user->isSuperadmin() ? true : null;
        });

        // Recuérdame: el guard usa 5 años por defecto; lo limitamos (30 días).
        Auth::guard('web')->setRememberDuration(config('auth.remember_lifetime'));

        // Suplantar entra con quietLogin(), que no toca la sesión. AuthenticateSession
        // compara el hash de contraseña guardado allí con el del usuario en curso y, al
        // no cuadrar, cierra la sesión: sin esto, suplantar acaba en la pantalla de login.
        Event::listen(TakeImpersonation::class, function (TakeImpersonation $evento) {
            $this->reasignarUsuarioDeLaSesion($evento->impersonated);
            $this->registrarEnElLog('Suplantación iniciada', $evento->impersonator, $evento->impersonated);
        });

        Event::listen(LeaveImpersonation::class, function (LeaveImpersonation $evento) {
            $this->reasignarUsuarioDeLaSesion($evento->impersonator);
            $this->registrarEnElLog('Suplantación terminada', $evento->impersonator, $evento->impersonated);
        });

        // Sandbox de correo: con EMAIL_SANDBOX=true todo va a EMAIL_SANDBOX_TO, sea cual
        // sea el Mailable o Notification que lo mande. El asunto deja constancia del
        // destinatario real, que si no se pierde (nadie lo ve fuera de este correo).
        Event::listen(MessageSending::class, function (MessageSending $evento) {
            if (! config('mail.sandbox.enabled') || ! config('mail.sandbox.to')) {
                return;
            }

            $destinoReal = implode(', ', array_map(
                fn ($direccion) => $direccion->getAddress(),
                [...$evento->message->getTo(), ...$evento->message->getCc(), ...$evento->message->getBcc()],
            ));

            $evento->message->to(new Address(config('mail.sandbox.to')));
            $evento->message->cc();
            $evento->message->bcc();

            if ($destinoReal) {
                $evento->message->subject('[SANDBOX → '.$destinoReal.'] '.$evento->message->getSubject());
            }
        });
    }

    private function registrarEnElLog(string $mensaje, $impersonador, $impersonado): void
    {
        Log::channel('impersonaciones')->info($mensaje, [
            'impersonador' => $impersonador->login . ' (id ' . $impersonador->id . ')',
            'impersonado'  => $impersonado->login . ' (id ' . $impersonado->id . ')',
            'ip'           => request()->ip(),
        ]);
    }

    private function reasignarUsuarioDeLaSesion($usuario): void
    {
        // El guard cachea el usuario de la petición y AuthenticateSession vuelve a guardar
        // su hash al terminar: sin setUser() ese guardado pisaría el de abajo.
        Auth::setUser($usuario);

        // La clave depende del guard en uso, que `auth:sanctum` cambia a `sanctum` en
        // caliente. Se limpian todas y se deja solo la del guard vigente.
        foreach (array_keys(config('auth.guards')) as $guard) {
            session()->forget('password_hash_' . $guard);
        }

        session()->put('password_hash_' . Auth::getDefaultDriver(), $usuario->getAuthPassword());
    }
}
