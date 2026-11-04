<?php

namespace App\Domain\Audit\Enums;

/**
 * Acciones administrativas relevantes que se registran en la auditoría.
 * Se irán ampliando conforme avancen las fases (facturas, pagos, tickets...).
 */
enum AuditAction: string
{
    case Login = 'login';
    case Logout = 'logout';

    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserBlocked = 'user.blocked';
    case UserUnblocked = 'user.unblocked';

    case ClientCreated = 'client.created';
    case ClientUpdated = 'client.updated';
    case ClientDeleted = 'client.deleted';
    case ClientStatusChanged = 'client.status_changed';
    case ClientCreditAdjusted = 'client.credit_adjusted';

    case ServerCreated = 'server.created';
    case ServerUpdated = 'server.updated';
    case ServerDeleted = 'server.deleted';

    case ServiceCreated = 'service.created';
    case ServiceUpdated = 'service.updated';
    case ServiceDeleted = 'service.deleted';

    case DomainCreated = 'domain.created';
    case DomainUpdated = 'domain.updated';
    case DomainDeleted = 'domain.deleted';

    case AddonCreated = 'addon.created';
    case AddonUpdated = 'addon.updated';
    case AddonDeleted = 'addon.deleted';

    case PlanCreated = 'plan.created';
    case PlanUpdated = 'plan.updated';
    case PlanDeleted = 'plan.deleted';

    case InvoiceCreated = 'invoice.created';
    case InvoiceUpdated = 'invoice.updated';
    case InvoiceStatusChanged = 'invoice.status_changed';
    case InvoiceDeleted = 'invoice.deleted';

    case PaymentRecorded = 'payment.recorded';

    case TicketCreated = 'ticket.created';
    case TicketReplied = 'ticket.replied';
    case TicketStatusChanged = 'ticket.status_changed';
    case TicketPriorityChanged = 'ticket.priority_changed';

    case NewsCreated = 'news.created';
    case NewsUpdated = 'news.updated';
    case NewsDeleted = 'news.deleted';

    case KbCategoryCreated = 'kb_category.created';
    case KbCategoryUpdated = 'kb_category.updated';
    case KbCategoryDeleted = 'kb_category.deleted';

    case KbArticleCreated = 'kb_article.created';
    case KbArticleUpdated = 'kb_article.updated';
    case KbArticleDeleted = 'kb_article.deleted';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Inicio de sesión',
            self::Logout => 'Cierre de sesión',
            self::UserCreated => 'Usuario creado',
            self::UserUpdated => 'Usuario actualizado',
            self::UserBlocked => 'Usuario bloqueado',
            self::UserUnblocked => 'Usuario desbloqueado',
            self::ClientCreated => 'Cliente creado',
            self::ClientUpdated => 'Cliente actualizado',
            self::ClientDeleted => 'Cliente eliminado',
            self::ClientStatusChanged => 'Estado de cliente cambiado',
            self::ClientCreditAdjusted => 'Saldo a favor ajustado',
            self::ServerCreated => 'Servidor creado',
            self::ServerUpdated => 'Servidor actualizado',
            self::ServerDeleted => 'Servidor eliminado',
            self::ServiceCreated => 'Servicio creado',
            self::ServiceUpdated => 'Servicio actualizado',
            self::ServiceDeleted => 'Servicio eliminado',
            self::DomainCreated => 'Dominio creado',
            self::DomainUpdated => 'Dominio actualizado',
            self::DomainDeleted => 'Dominio eliminado',
            self::AddonCreated => 'Complemento creado',
            self::AddonUpdated => 'Complemento actualizado',
            self::AddonDeleted => 'Complemento eliminado',
            self::PlanCreated => 'Plan creado',
            self::PlanUpdated => 'Plan actualizado',
            self::PlanDeleted => 'Plan eliminado',
            self::InvoiceCreated => 'Factura creada',
            self::InvoiceUpdated => 'Factura actualizada',
            self::InvoiceStatusChanged => 'Estado de factura cambiado',
            self::InvoiceDeleted => 'Factura eliminada',
            self::PaymentRecorded => 'Pago registrado',
            self::TicketCreated => 'Ticket creado',
            self::TicketReplied => 'Ticket respondido',
            self::TicketStatusChanged => 'Estado de ticket cambiado',
            self::TicketPriorityChanged => 'Prioridad de ticket cambiada',
            self::NewsCreated => 'Noticia creada',
            self::NewsUpdated => 'Noticia actualizada',
            self::NewsDeleted => 'Noticia eliminada',
            self::KbCategoryCreated => 'Categoría KB creada',
            self::KbCategoryUpdated => 'Categoría KB actualizada',
            self::KbCategoryDeleted => 'Categoría KB eliminada',
            self::KbArticleCreated => 'Artículo KB creado',
            self::KbArticleUpdated => 'Artículo KB actualizado',
            self::KbArticleDeleted => 'Artículo KB eliminado',
        };
    }

    /**
     * Lista de opciones para filtros (valor => etiqueta).
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
