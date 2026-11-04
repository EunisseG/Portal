<?php

namespace App\Domain\Clients\Services;

use App\Integrations\Contracts\MailGateway;
use App\Models\User;
use Illuminate\Support\Facades\Password;

/**
 * Envía al usuario un correo con un enlace para establecer su contraseña.
 * Usa el broker de contraseñas de Laravel para generar un token seguro; la
 * contraseña nunca viaja por correo. El envío pasa por el MailGateway (queda
 * registrado y, en producción, sale por SMTP).
 */
class SendAccountSetupLink
{
    public function __construct(private readonly MailGateway $mail) {}

    /**
     * Envía el enlace para establecer/restablecer la contraseña.
     *
     * @param  User  $user  Usuario dueño de la cuenta (el token se liga a su email de login).
     * @param  string|null  $toEmail  Dirección a la que se envía el correo. Si es null,
     *                                 se usa el correo principal del usuario. Permite
     *                                 enviarlo a un correo secundario de recuperación.
     * @param  bool  $isReset  true para redactar el mensaje como "restablecer" (reenvío),
     *                         false para el alta inicial ("configura tu cuenta").
     */
    public function send(User $user, ?string $toEmail = null, bool $isReset = false): void
    {
        $token = Password::broker()->createToken($user);

        // El token del broker se valida contra el email de login del usuario,
        // así que el enlace siempre lleva el correo principal, aunque el correo
        // se entregue en una dirección secundaria.
        $link = route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], absolute: true);

        $subject = $isReset
            ? 'Restablece tu contraseña · Portal WORTEV'
            : 'Configura el acceso a tu cuenta · Portal WORTEV';

        $intro = $isReset
            ? 'Recibimos una solicitud para restablecer tu contraseña del Portal WORTEV.'
            : 'Se creó tu cuenta en el Portal WORTEV.';

        $body = implode("\n", [
            'Hola '.$user->name.',',
            '',
            $intro,
            'Para continuar, establece tu contraseña desde el siguiente enlace:',
            '',
            $link,
            '',
            'Por seguridad, este enlace caduca en 60 minutos. Si expira, usa la',
            'opción "¿Olvidaste tu contraseña?" en la pantalla de acceso.',
            '',
            'Equipo WORTEV',
        ]);

        $this->mail->send($toEmail ?: $user->email, $subject, $body, [
            'type' => $isReset ? 'password_reset' : 'account_setup',
            'user_id' => $user->id,
        ]);
    }
}
