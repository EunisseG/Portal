<?php

namespace App\Domain\Auth\Support;

use Illuminate\Support\Str;

/**
 * Etiquetas legibles (en español) para los permisos del sistema y para los
 * grupos (prefijo de dominio). Los permisos se almacenan como strings tipo
 * "invoices.view"; aquí se traducen para mostrarlos en la UI sin cambiar su
 * identificador real.
 */
final class PermissionLabels
{
    /**
     * Etiqueta por grupo (prefijo antes del primer punto).
     *
     * @var array<string, string>
     */
    private const GROUPS = [
        'users' => 'Usuarios',
        'roles' => 'Roles',
        'permissions' => 'Permisos',
        'clients' => 'Clientes',
        'client_credit' => 'Saldo de cliente',
        'sales_reps' => 'Ejecutivos de ventas',
        'services' => 'Servicios',
        'plans' => 'Planes',
        'domains' => 'Dominios',
        'addons' => 'Complementos',
        'servers' => 'Servidores',
        'invoices' => 'Facturas',
        'payments' => 'Pagos',
        'payment_proofs' => 'Comprobantes de pago',
        'tickets' => 'Tickets de clientes',
        'internal_tickets' => 'Tickets internos',
        'internal_areas' => 'Áreas internas',
        'employees' => 'Empleados',
        'news' => 'Noticias',
        'kb' => 'Base de conocimiento',
        'sla' => 'SLA / Soporte',
        'notifications' => 'Notificaciones',
        'audit' => 'Auditoría',
        'security' => 'Seguridad',
        'integrations' => 'Integraciones',
        'client' => 'Portal del cliente',
    ];

    /**
     * Etiqueta por permiso completo.
     *
     * @var array<string, string>
     */
    private const PERMISSIONS = [
        'users.view' => 'Ver usuarios',
        'users.create' => 'Crear usuarios',
        'users.update' => 'Editar usuarios',
        'users.block' => 'Bloquear usuarios',
        'roles.manage' => 'Gestionar roles',
        'permissions.manage' => 'Gestionar permisos',
        'clients.view' => 'Ver clientes',
        'clients.manage' => 'Gestionar clientes',
        'client_credit.manage' => 'Gestionar saldo de cliente',
        'sales_reps.manage' => 'Gestionar ejecutivos de ventas',
        'services.view' => 'Ver servicios',
        'services.manage' => 'Gestionar servicios',
        'plans.manage' => 'Gestionar planes',
        'domains.view' => 'Ver dominios',
        'domains.manage' => 'Gestionar dominios',
        'addons.manage' => 'Gestionar complementos',
        'servers.manage' => 'Gestionar servidores',
        'invoices.view' => 'Ver facturas',
        'invoices.manage' => 'Gestionar facturas',
        'payments.view' => 'Ver pagos',
        'payments.record' => 'Registrar pagos',
        'payment_proofs.manage' => 'Gestionar comprobantes de pago',
        'tickets.view' => 'Ver tickets de clientes',
        'tickets.manage' => 'Gestionar tickets de clientes',
        'tickets.reply' => 'Responder tickets de clientes',
        'internal_tickets.view' => 'Ver tickets internos',
        'internal_tickets.manage' => 'Gestionar tickets internos',
        'internal_tickets.reply' => 'Responder tickets internos',
        'internal_areas.manage' => 'Gestionar áreas internas',
        'employees.manage' => 'Gestionar empleados',
        'sla.manage' => 'Gestionar SLA',
        'news.view' => 'Ver noticias',
        'news.manage' => 'Gestionar noticias',
        'kb.view' => 'Ver base de conocimiento',
        'kb.manage' => 'Gestionar base de conocimiento',
        'notifications.manage' => 'Gestionar notificaciones',
        'audit.view' => 'Ver auditoría',
        'security.view' => 'Ver seguridad',
        'security.manage' => 'Gestionar seguridad / dependencias',
        'integrations.manage' => 'Gestionar integraciones',
        'client.invoices.view' => 'Portal: ver facturas',
        'client.proofs.upload' => 'Portal: subir comprobantes',
        'client.tickets.view' => 'Portal: ver tickets',
        'client.tickets.create' => 'Portal: crear tickets',
        'client.services.view' => 'Portal: ver servicios',
        'client.domains.view' => 'Portal: ver dominios',
        'client.orders.create' => 'Portal: hacer pedidos',
    ];

    public static function forPermission(string $permission): string
    {
        return self::PERMISSIONS[$permission] ?? $permission;
    }

    public static function forGroup(string $group): string
    {
        return self::GROUPS[$group] ?? Str::headline($group);
    }

    /** Grupo (prefijo de dominio) al que pertenece un permiso. */
    public static function groupOf(string $permission): string
    {
        return Str::before($permission, '.');
    }
}
