<?php

namespace App\Providers;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Policies\InvoicePolicy;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Policies\ClientPolicy;
use App\Domain\Content\Models\KbArticle;
use App\Domain\Content\Models\KbCategory;
use App\Domain\Content\Models\News;
use App\Domain\Content\Policies\KbPolicy;
use App\Domain\Content\Policies\NewsPolicy;
use App\Domain\Services\Models\Addon;
use App\Domain\Services\Models\Domain;
use App\Domain\Services\Models\Plan;
use App\Domain\Services\Models\Server;
use App\Domain\Services\Models\Service;
use App\Domain\Services\Policies\AddonPolicy;
use App\Domain\Services\Policies\DomainPolicy;
use App\Domain\Services\Policies\PlanPolicy;
use App\Domain\Services\Policies\ServerPolicy;
use App\Domain\Services\Policies\ServicePolicy;
use App\Domain\Auth\Models\FailedLoginAttempt;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Policies\TicketPolicy;
use App\Domain\Users\Policies\UserPolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        // Registro explícito de policies (modelos en namespaces de dominio).
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Server::class, ServerPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(Domain::class, DomainPolicy::class);
        Gate::policy(Addon::class, AddonPolicy::class);
        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(\App\Domain\Billing\Models\PaymentProof::class, \App\Domain\Billing\Policies\PaymentProofPolicy::class);
        Gate::policy(\App\Domain\Billing\Models\Payment::class, \App\Domain\Billing\Policies\PaymentPolicy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(News::class, NewsPolicy::class);
        Gate::policy(KbCategory::class, KbPolicy::class);
        Gate::policy(KbArticle::class, KbPolicy::class);

        // El super_admin tiene acceso a todo, sin necesidad de asignarle
        // cada permiso de forma individual.
        Gate::before(function (User $user, string $ability) {
            return $user->hasRole(RoleName::SuperAdmin->value) ? true : null;
        });

        // Auditoría de inicio y cierre de sesión.
        Event::listen(Login::class, function (Login $event): void {
            app(AuditLogger::class)->log(AuditAction::Login, $event->user);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user) {
                app(AuditLogger::class)->log(AuditAction::Logout, $event->user);
            }
        });

        // Registro de intentos de inicio de sesión fallidos.
        Event::listen(Failed::class, function (Failed $event): void {
            FailedLoginAttempt::create([
                'email' => (string) ($event->credentials['email'] ?? 'desconocido'),
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255) ?: null,
                'attempted_at' => now(),
            ]);
        });

        // Puente genérico eventos de dominio → integraciones. Cualquier evento
        // que implemente IntegrationEvent se enruta a las integraciones activas
        // suscritas, sin acoplar los módulos de negocio a los proveedores.
        Event::listen(
            \App\Integrations\Events\IntegrationEvent::class,
            [\App\Integrations\Events\IntegrationEventSubscriber::class, 'handle'],
        );

        // Registro de VENTA NUEVA: al crear un servicio, dominio o complemento
        // se registra un evento comercial para el ejecutivo del cliente. Se
        // engancha al evento 'created' del modelo para cubrir TODOS los flujos
        // de alta (formularios, importaciones, servicio inicial, etc.) sin
        // tener que tocar cada punto de creación. El recorder es idempotente y
        // nunca rompe el flujo si no puede resolver cliente/monto.
        $recordNewSale = function (Model $subject): void {
            app(\App\Domain\Sales\Services\SalesEventRecorder::class)
                ->recordNewSale($subject, $subject->created_at);
        };
        Service::created($recordNewSale);
        Domain::created($recordNewSale);
        Addon::created($recordNewSale);
    }
}
