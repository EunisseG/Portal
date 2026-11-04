<?php

use App\Domain\Auth\Enums\RoleName;
use App\Http\Controllers\Client\DashboardController;
use App\Livewire\Client\Account\AccountContacts;
use App\Livewire\Client\Account\ChangePassword;
use App\Livewire\Client\Account\EmailsReceived;
use App\Livewire\Client\Account\ProfileData;
use App\Livewire\Client\Account\SecuritySettings;
use App\Livewire\Client\Domains\DomainList;
use App\Livewire\Client\Invoices\InvoiceList;
use App\Livewire\Client\Kb\KbList;
use App\Livewire\Client\Maintenance\MaintenanceList;
use App\Livewire\Client\Kb\KbShow;
use App\Livewire\Client\News\NewsList;
use App\Livewire\Client\News\NewsShow;
use App\Livewire\Client\Services\ServiceList;
use App\Livewire\Client\Services\ServiceShow;
use App\Livewire\Client\Tickets\TicketCreate;
use App\Livewire\Client\Tickets\TicketIndex as ClientTicketIndex;
use App\Livewire\Client\Tickets\TicketShow as ClientTicketShow;
use Illuminate\Support\Facades\Route;

/*
| Área de cliente (/client)
| Accesible solo para usuarios con rol "client". Solo lectura.
*/
Route::middleware(['auth', 'verified', 'not.blocked', 'role:'.RoleName::Client->value])
    ->prefix('client')
    ->name('client.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/servicios', ServiceList::class)->name('services.index');
        Route::get('/servicios/{service}', ServiceShow::class)->name('services.show');
        Route::get('/dominios', DomainList::class)->name('domains.index');
        Route::get('/facturas', InvoiceList::class)->name('invoices.index');

        Route::get('/tickets', ClientTicketIndex::class)->name('tickets.index');
        Route::get('/tickets/nuevo', TicketCreate::class)->name('tickets.create');
        Route::get('/tickets/{ticket}', ClientTicketShow::class)->name('tickets.show');

        Route::get('/noticias', NewsList::class)->name('news.index');
        Route::get('/noticias/{slug}', NewsShow::class)->name('news.show');

        Route::get('/mantenimientos', MaintenanceList::class)->name('maintenances.index');

        Route::get('/kb', KbList::class)->name('kb.index');
        Route::get('/kb/{slug}', KbShow::class)->name('kb.show');

        // Mi Cuenta: perfil, contactos/sub-cuentas, contraseña, seguridad y correos.
        Route::prefix('mi-cuenta')->name('account.')->group(function () {
            Route::get('/', ProfileData::class)->name('profile');
            Route::get('/contactos', AccountContacts::class)->name('contacts');
            Route::get('/contrasena', ChangePassword::class)->name('password');
            Route::get('/seguridad', SecuritySettings::class)->name('security');
            Route::get('/correos', EmailsReceived::class)->name('emails');
        });
    });
