<?php

namespace App\Integrations\Registry;

/**
 * Diccionario de etiquetas y descripciones en español para los campos de
 * configuración, credenciales y eventos de las integraciones.
 *
 * Las CLAVES técnicas (phone_number_id, access_token, etc.) NO cambian: son
 * las que espera cada proveedor. Aquí solo se traduce lo que ve el
 * administrador en el panel, con una ayuda de "para qué sirve".
 */
class IntegrationFieldCatalog
{
    /**
     * Campos: clave => [label, help].
     *
     * @var array<string, array{label: string, help: string}>
     */
    private const FIELDS = [
        // --- WhatsApp (Meta Cloud API) ---
        'phone_number_id' => [
            'label' => 'ID del número de teléfono',
            'help' => 'Identificador del número de WhatsApp en Meta (WhatsApp Cloud API). Lo obtienes en el panel de desarrolladores de Meta, en tu app de WhatsApp.',
        ],
        'api_version' => [
            'label' => 'Versión de la API',
            'help' => 'Versión de la Graph API de Meta a usar (por ejemplo v20.0). Si lo dejas vacío se usa la predeterminada.',
        ],
        'template_language' => [
            'label' => 'Idioma de las plantillas',
            'help' => 'Código de idioma de las plantillas aprobadas en Meta (por ejemplo es_MX). Debe coincidir con el idioma con que registraste la plantilla.',
        ],
        'template_renewal' => [
            'label' => 'Plantilla: recordatorio de vencimiento',
            'help' => 'Nombre de la plantilla de WhatsApp (aprobada en Meta) para avisar que un servicio o dominio está por vencer.',
        ],
        'template_ticket_reply' => [
            'label' => 'Plantilla: respuesta de ticket',
            'help' => 'Nombre de la plantilla para avisar al cliente que su ticket fue respondido.',
        ],
        'template_maintenance' => [
            'label' => 'Plantilla: aviso de mantenimiento',
            'help' => 'Nombre de la plantilla para avisar de un mantenimiento programado.',
        ],
        'access_token' => [
            'label' => 'Token de acceso',
            'help' => 'Token de acceso permanente de la app de WhatsApp en Meta. Se usa para autenticar cada envío. Se guarda cifrado.',
        ],

        // --- Monday.com ---
        'board_id' => [
            'label' => 'ID del tablero',
            'help' => 'Identificador del tablero (board) de Monday donde se crearán los elementos (tickets sincronizados).',
        ],
        'api_token' => [
            'label' => 'Token de API',
            'help' => 'Token personal de la API de Monday (o de WHM, según la integración). Se guarda cifrado.',
        ],

        // --- SMTP ---
        'host' => [
            'label' => 'Servidor (host)',
            'help' => 'Dirección del servidor SMTP de salida (por ejemplo smtp.tudominio.com).',
        ],
        'port' => [
            'label' => 'Puerto',
            'help' => 'Puerto del servidor SMTP (habitualmente 587 con TLS o 465 con SSL).',
        ],
        'encryption' => [
            'label' => 'Cifrado',
            'help' => 'Tipo de cifrado de la conexión: tls o ssl.',
        ],
        'from_address' => [
            'label' => 'Correo del remitente',
            'help' => 'Dirección que aparecerá como remitente de los correos que envíe el sistema.',
        ],
        'from_name' => [
            'label' => 'Nombre del remitente',
            'help' => 'Nombre visible del remitente (por ejemplo "Soporte WORTEV").',
        ],
        'username' => [
            'label' => 'Usuario',
            'help' => 'Usuario de autenticación del servidor (SMTP u Openpay, según la integración).',
        ],
        'password' => [
            'label' => 'Contraseña',
            'help' => 'Contraseña de autenticación del servidor SMTP. Se guarda cifrada.',
        ],

        // --- WHM / cPanel ---
        'base_url' => [
            'label' => 'URL base del servidor',
            'help' => 'Dirección del WHM incluyendo el puerto, por ejemplo https://tu-servidor:2087.',
        ],
        'whm_user' => [
            'label' => 'Usuario de WHM',
            'help' => 'Usuario administrador de WHM (normalmente root).',
        ],

        // --- Dominios (Namecheap) ---
        'api_user' => [
            'label' => 'Usuario de API',
            'help' => 'Nombre de usuario de la cuenta de Namecheap habilitado para la API.',
        ],
        'client_ip' => [
            'label' => 'IP autorizada',
            'help' => 'Dirección IP pública autorizada en el panel de Namecheap para consumir su API.',
        ],
        'sandbox' => [
            'label' => 'Modo de pruebas (sandbox)',
            'help' => 'Marca 1 para usar el entorno de pruebas del proveedor; 0 o vacío para producción.',
        ],
        'api_key' => [
            'label' => 'Clave de API',
            'help' => 'Clave secreta de la API de Namecheap. Se guarda cifrada.',
        ],

        // --- Pagos ---
        'currency' => [
            'label' => 'Moneda',
            'help' => 'Código de moneda de los cobros (por ejemplo MXN o USD).',
        ],
        'webhook_secret' => [
            'label' => 'Secreto del webhook',
            'help' => 'Clave secreta para validar la firma de las notificaciones (webhooks) que envía el proveedor. Se guarda cifrada.',
        ],
        'merchant_id' => [
            'label' => 'ID de comercio',
            'help' => 'Identificador de tu comercio en Openpay.',
        ],
        'private_key' => [
            'label' => 'Llave privada',
            'help' => 'Llave privada de Openpay usada para autenticar las operaciones. Se guarda cifrada.',
        ],
        'client_id' => [
            'label' => 'Client ID',
            'help' => 'Identificador de cliente (client_id) de tu aplicación en PayPal.',
        ],
        'client_secret' => [
            'label' => 'Client Secret',
            'help' => 'Secreto de cliente (client_secret) de tu aplicación en PayPal. Se guarda cifrado.',
        ],
        'webhook_id' => [
            'label' => 'ID del webhook',
            'help' => 'Identificador del webhook configurado en PayPal, usado para verificar las notificaciones.',
        ],

        // --- Slack ---
        'channel' => [
            'label' => 'Canal',
            'help' => 'Canal de Slack donde se publicarán los avisos (opcional; por defecto el del webhook).',
        ],
        'icon_emoji' => [
            'label' => 'Emoji del ícono',
            'help' => 'Emoji que se mostrará como ícono del mensaje (opcional), por ejemplo :bell:.',
        ],
        'webhook_url' => [
            'label' => 'URL del webhook',
            'help' => 'URL del Incoming Webhook de Slack a la que se envían los avisos. Se guarda cifrada.',
        ],

        // --- API personalizada ---
        'endpoint' => [
            'label' => 'Endpoint',
            'help' => 'Ruta o URL del recurso a consultar de la API externa.',
        ],
        'method' => [
            'label' => 'Método HTTP',
            'help' => 'Método de la petición: GET, POST, PUT, etc.',
        ],
        'auth_type' => [
            'label' => 'Tipo de autenticación',
            'help' => 'Cómo se autentica la API: none, bearer, basic o api_key.',
        ],
        'timeout' => [
            'label' => 'Tiempo de espera (s)',
            'help' => 'Segundos máximos de espera por respuesta antes de cancelar la petición.',
        ],
        'retries' => [
            'label' => 'Reintentos',
            'help' => 'Número de reintentos si la petición falla.',
        ],
        'auth_token' => [
            'label' => 'Token de autenticación',
            'help' => 'Token para autenticar la API externa (bearer o api_key). Se guarda cifrado.',
        ],
    ];

    /**
     * Eventos: clave => [label, help].
     *
     * @var array<string, array{label: string, help: string}>
     */
    private const EVENTS = [
        'TicketCreated' => ['label' => 'Ticket creado', 'help' => 'Cuando un cliente abre un nuevo ticket.'],
        'TicketStatusChanged' => ['label' => 'Estado de ticket cambiado', 'help' => 'Cuando cambia el estado de un ticket.'],
        'TicketClosed' => ['label' => 'Ticket cerrado', 'help' => 'Cuando se cierra un ticket.'],
        'ServiceExpiring' => ['label' => 'Servicio por vencer', 'help' => 'Cuando un servicio está próximo a su fecha de vencimiento.'],
        'DomainExpiring' => ['label' => 'Dominio por vencer', 'help' => 'Cuando un dominio está próximo a su fecha de vencimiento.'],
        'PaymentApproved' => ['label' => 'Pago aprobado', 'help' => 'Cuando el proveedor confirma un pago aprobado.'],
        'PaymentRejected' => ['label' => 'Pago rechazado', 'help' => 'Cuando el proveedor reporta un pago rechazado.'],
    ];

    /**
     * Etiqueta en español de un campo. Si no está en el diccionario, humaniza
     * la clave (reemplaza guiones bajos y capitaliza).
     */
    public static function fieldLabel(string $field): string
    {
        return self::FIELDS[$field]['label'] ?? ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * Descripción de ayuda de un campo (o null si no hay).
     */
    public static function fieldHelp(string $field): ?string
    {
        return self::FIELDS[$field]['help'] ?? null;
    }

    /**
     * Etiqueta en español de un evento. Si no está, devuelve el nombre crudo.
     */
    public static function eventLabel(string $event): string
    {
        return self::EVENTS[$event]['label'] ?? $event;
    }

    /**
     * Descripción de ayuda de un evento (o null si no hay).
     */
    public static function eventHelp(string $event): ?string
    {
        return self::EVENTS[$event]['help'] ?? null;
    }
}
