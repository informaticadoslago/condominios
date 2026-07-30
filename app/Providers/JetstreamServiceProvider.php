<?php

namespace App\Providers;

use Livewire\Livewire;
use Laravel\Jetstream\Jetstream;
use App\Actions\Jetstream\DeleteUser;
use Illuminate\Support\ServiceProvider;
use App\Livewire\Profile\UpdateProfileInformationForm;

class JetstreamServiceProvider extends ServiceProvider
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
        $this->configurePermissions();

        Jetstream::deleteUsersUsing(DeleteUser::class);

        Livewire::component('profile.update-profile-information-form', UpdateProfileInformationForm::class);
        // Livewire::component('profile.update-password-form', UpdatePasswordForm::class);
        // Livewire::component('profile.delete-user-form', DeleteUserForm::class);
        // Livewire::component('profile.two-factor-authentication-form', TwoFactorAuthenticationForm::class);
        // Livewire::component('profile.logout-other-browser-sessions-form', LogoutOtherBrowserSessionsForm::class);




    }

    /**
     * Configure the permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions(['read']);

        Jetstream::permissions([
            'create',
            'read',
            'update',
            'delete',
        ]);
    }
}
