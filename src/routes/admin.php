<?php

use App\Domain\Auth\Enums\RoleName;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DataTransferController;
use App\Livewire\Admin\Audit\AuditIndex;
use App\Livewire\Admin\Content\MaintenanceIndex;
use App\Livewire\Admin\Security\FailedLoginIndex;
use App\Livewire\Admin\Clients\ClientCreditPanel;
use App\Livewire\Admin\Clients\ClientForm;
use App\Livewire\Admin\Clients\ClientIndex;
use App\Livewire\Admin\Clients\ClientPanel;
use App\Livewire\Admin\Clients\ClientShow;
use App\Livewire\Admin\Domains\DomainForm;
use App\Livewire\Admin\Domains\DomainIndex;
use App\Livewire\Admin\Billing\PaymentOrderIndex;
use App\Livewire\Admin\Invoices\InvoiceForm;
use App\Livewire\Admin\Invoices\InvoiceIndex;
use App\Livewire\Admin\Kb\KbArticleForm;
use App\Livewire\Admin\Kb\KbArticleIndex;
use App\Livewire\Admin\Kb\KbCategoryForm;
use App\Livewire\Admin\Kb\KbCategoryIndex;
use App\Livewire\Admin\News\NewsForm;
use App\Livewire\Admin\News\NewsIndex;
use App\Livewire\Admin\Plans\PlanForm;
use App\Livewire\Admin\Plans\PlanIndex;
use App\Livewire\Admin\Renewals\RenewalsIndex;
use App\Livewire\Admin\Reports\ReportIndex;
use App\Livewire\Admin\Roles\RoleIndex;
use App\Livewire\Admin\Sales\SalesRepIndex;
use App\Livewire\Admin\Support\SlaIndex;
use App\Livewire\Admin\Support\SlaPolicyIndex;
use App\Livewire\Admin\Servers\ServerForm;
use App\Livewire\Admin\Servers\ServerIndex;
use App\Livewire\Admin\Services\ServiceForm;
use App\Livewire\Admin\Activity\ClientLoginHistory;
use App\Livewire\Admin\Activity\EmailLogIndex;
use App\Livewire\Admin\Activity\StaffLoginHistory;
use App\Livewire\Admin\Satisfaction\SatisfactionIndex;
use App\Livewire\Admin\Support\CannedResponseIndex;
use App\Livewire\Admin\Integrations\IntegrationCatalog;
use App\Livewire\Admin\Integrations\IntegrationConfigure;
use App\Livewire\Admin\Integrations\IntegrationIndex;
use App\Livewire\Admin\Integrations\IntegrationLogIndex;
use App\Livewire\Admin\System\BrandingSettings;
use App\Livewire\Admin\System\CurrencyIndex;
use App\Livewire\Admin\System\DependencyAudit;
use App\Livewire\Admin\System\OperationModeSettings;
use App\Livewire\Admin\System\SmtpSettingsForm;
use App\Livewire\Admin\Services\AddonCatalogIndex;
use App\Livewire\Admin\Services\AddonIndex;
use App\Livewire\Admin\Services\ProductIndex;
use App\Livewire\Admin\Services\PromotionIndex;
use App\Livewire\Admin\Services\ServiceIndex;
use App\Livewire\Admin\Services\ServiceShow;
use App\Livewire\Admin\Tickets\TicketCreate;
use App\Livewire\Admin\Tickets\TicketIndex;
use App\Livewire\Admin\Tickets\TicketShow;
use App\Livewire\Admin\Users\UserForm;
use App\Livewire\Admin\Users\UserIndex;
use Illuminate\Support\Facades\Route;

/*
| Área administrativa (/admin)
| Accesible para roles administrativos (super_admin, admin, support, billing).
*/
$adminRoles = implode('|', array_map(
    fn (RoleName $role) => $role->value,
    RoleName::adminRoles(),
));

Route::middleware(['auth', 'verified', 'not.blocked', 'role:'.$adminRoles])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        // Usuarios
        Route::get('/usuarios', UserIndex::class)->name('users.index');
        Route::get('/usuarios/nuevo', UserForm::class)->name('users.create');
        Route::get('/usuarios/{user}/editar', UserForm::class)->name('users.edit');

        // Clientes
        Route::get('/clientes', ClientIndex::class)->name('clients.index');
        Route::get('/clientes/nuevo', ClientForm::class)->name('clients.create');
        Route::get('/clientes/{client}/panel', ClientPanel::class)->name('clients.panel');
        Route::get('/clientes/{client}/saldo', ClientCreditPanel::class)->name('clients.credit');
        Route::get('/clientes/{client}/editar', ClientForm::class)->name('clients.edit');
        Route::get('/clientes/{client}', ClientShow::class)->name('clients.show');

        // Servidores
        Route::get('/servidores', ServerIndex::class)->name('servers.index');
        Route::get('/servidores/nuevo', ServerForm::class)->name('servers.create');
        Route::get('/servidores/{server}/editar', ServerForm::class)->name('servers.edit');

        // Planes (catálogo)
        Route::get('/planes', PlanIndex::class)->name('plans.index');
        Route::get('/planes/nuevo', PlanForm::class)->name('plans.create');
        Route::get('/planes/{plan}/editar', PlanForm::class)->name('plans.edit');

        // Servicios
        Route::get('/servicios', ServiceIndex::class)->name('services.index');
        Route::get('/servicios/nuevo', ServiceForm::class)->name('services.create');
        Route::get('/servicios/{service}', ServiceShow::class)->name('services.show');
        Route::get('/servicios/{service}/editar', ServiceForm::class)->name('services.edit');

        // Catálogo de productos
        Route::get('/productos', ProductIndex::class)->name('products.index');

        // Complementos: catálogo + desplegados en una sola pantalla con pestañas.
        // Ambas rutas apuntan al mismo componente (se elige pestaña con ?tab=).
        Route::get('/catalogo-complementos', AddonCatalogIndex::class)->name('addon-catalog.index');
        Route::get('/complementos', AddonIndex::class)->name('addons.index');

        // Promociones de productos (catálogo)
        Route::get('/promociones', PromotionIndex::class)->name('promotions.index');

        // Dominios
        Route::get('/dominios', DomainIndex::class)->name('domains.index');
        Route::get('/dominios/nuevo', DomainForm::class)->name('domains.create');
        Route::get('/dominios/{domain}/editar', DomainForm::class)->name('domains.edit');

        // Facturas
        Route::get('/facturas', InvoiceIndex::class)->name('invoices.index');
        Route::get('/facturas/nueva', InvoiceForm::class)->name('invoices.create');
        Route::get('/facturas/{invoice}/editar', InvoiceForm::class)->name('invoices.edit');

        // Órdenes de pago en línea (folios de cobro; solo lectura).
        Route::get('/ordenes-pago', PaymentOrderIndex::class)->name('payment-orders.index');

        // Gráficas / Reportes (concentrador de distribuciones e ingresos).
        Route::get('/graficas', ReportIndex::class)->name('reports.index');
        Route::get('/graficas-export', [DataTransferController::class, 'exportReport'])->name('reports.export');

        // Tickets de clientes
        Route::get('/tickets', TicketIndex::class)->name('tickets.index');
        Route::get('/tickets/nuevo', TicketCreate::class)->name('tickets.create');
        Route::get('/tickets/{ticket}', TicketShow::class)->name('tickets.show');

        // Tickets internos (levantados por empleados)
        Route::get('/tickets-internos', \App\Livewire\Admin\Tickets\InternalTicketIndex::class)->name('internal-tickets.index');
        Route::get('/tickets-internos/{ticket}', \App\Livewire\Admin\Tickets\InternalTicketShow::class)->name('internal-tickets.show');

        // Empleados y áreas internas
        Route::get('/empleados', \App\Livewire\Admin\Internal\EmployeeIndex::class)->name('employees.index');
        Route::get('/areas-internas', \App\Livewire\Admin\Internal\InternalAreaIndex::class)->name('internal-areas.index');

        // Satisfacción del cliente (CSAT)
        Route::get('/satisfaccion', SatisfactionIndex::class)->name('satisfaction.index');

        // SLA / Soporte: métricas de atención y configuración de políticas SLA
        Route::get('/sla', SlaIndex::class)->name('sla.index');
        Route::get('/sla/politicas', SlaPolicyIndex::class)->name('sla.policies');

        // Respuestas preparadas (canned responses)
        Route::get('/respuestas-preparadas', CannedResponseIndex::class)->name('canned.index');

        // Tipos de moneda (catálogo de sistema)
        Route::get('/monedas', CurrencyIndex::class)->name('currencies.index');

        // Ejecutivos de ventas (catálogo; se ligan a los clientes)
        Route::get('/ejecutivos', SalesRepIndex::class)->name('sales-reps.index');

        // Configuración de correo (SMTP)
        Route::get('/configuracion/smtp', SmtpSettingsForm::class)->name('settings.smtp');

        // Apariencia / marca (nombre, logo, favicon)
        Route::get('/configuracion/apariencia', BrandingSettings::class)->name('settings.branding');

        // Modo de operación (pagos / renovación de dominios: manual o automático)
        Route::get('/configuracion/operacion', OperationModeSettings::class)->name('settings.operation');

        // Dependencias / Seguridad: auditoría de paquetes Composer y npm
        Route::get('/sistema/dependencias', DependencyAudit::class)->name('system.dependencies');

        // Encuestas de satisfacción (editor de preguntas, clientes y empleados)
        Route::get('/encuestas', \App\Livewire\Admin\System\SurveyFormIndex::class)->name('surveys.index');

        // Integraciones
        Route::get('/integraciones', IntegrationIndex::class)->name('integrations.index');
        Route::get('/integraciones/catalogo', IntegrationCatalog::class)->name('integrations.catalog');
        Route::get('/integraciones/logs', IntegrationLogIndex::class)->name('integrations.logs');
        Route::get('/integraciones/{integration}', IntegrationConfigure::class)->name('integrations.configure');

        // Actividad
        Route::get('/actividad/correos', EmailLogIndex::class)->name('activity.emails');
        Route::get('/actividad/accesos-personal', StaffLoginHistory::class)->name('activity.staff-logins');
        Route::get('/actividad/accesos-cliente', ClientLoginHistory::class)->name('activity.client-logins');

        // Vencimientos
        Route::get('/vencimientos', RenewalsIndex::class)->name('renewals.index');

        // Mantenimientos (anuncios programados)
        Route::get('/mantenimientos', MaintenanceIndex::class)->name('maintenances.index');

        // Noticias y anuncios
        Route::get('/noticias', NewsIndex::class)->name('news.index');
        Route::get('/noticias/nueva', NewsForm::class)->name('news.create');
        Route::get('/noticias/{news}/editar', NewsForm::class)->name('news.edit');

        // Base de conocimientos
        Route::get('/kb/categorias', KbCategoryIndex::class)->name('kb.categories.index');
        Route::get('/kb/categorias/nueva', KbCategoryForm::class)->name('kb.categories.create');
        Route::get('/kb/categorias/{category}/editar', KbCategoryForm::class)->name('kb.categories.edit');
        Route::get('/kb/articulos', KbArticleIndex::class)->name('kb.articles.index');
        Route::get('/kb/articulos/nuevo', KbArticleForm::class)->name('kb.articles.create');
        Route::get('/kb/articulos/{article}/editar', KbArticleForm::class)->name('kb.articles.edit');

        // Roles y permisos (solo lectura)
        Route::get('/roles', RoleIndex::class)->name('roles.index');

        // Auditoría
        Route::get('/auditoria', AuditIndex::class)->name('audit.index');

        // Seguridad: intentos de inicio de sesión fallidos
        Route::get('/seguridad/accesos-fallidos', FailedLoginIndex::class)->name('security.failed-logins');

        // Importar / exportar Excel
        Route::get('/clientes-export', [DataTransferController::class, 'exportClients'])->name('clients.export');
        Route::post('/clientes-import', [DataTransferController::class, 'importClients'])->name('clients.import');
        Route::get('/empleados-export', [DataTransferController::class, 'exportEmployees'])->name('employees.export');
        Route::get('/ejecutivos-export', [DataTransferController::class, 'exportSalesReps'])->name('sales-reps.export');
        Route::get('/servicios-export', [DataTransferController::class, 'exportServices'])->name('services.export');
        Route::post('/servicios-import', [DataTransferController::class, 'importServices'])->name('services.import');
    });
