<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Correo de verificación de cuenta enviado por la cola (no dentro de la
 * petición). Así el registro responde al instante aunque el SMTP esté lento o
 * bloqueado, en vez de quedarse esperando hasta un 504. Si el envío falla, el
 * usuario puede pedir el reenvío desde la pantalla de verificación.
 */
class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /** Reintentos ante un fallo temporal del SMTP. */
    public int $tries = 3;

    /** Espera (segundos) entre reintentos. */
    public array $backoff = [30, 120];
}
