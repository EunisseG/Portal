<?php

namespace App\Domain\Notifications\Jobs;

use App\Domain\Notifications\Models\Reminder;
use App\Domain\Services\Models\Addon;
use App\Domain\Services\Models\Domain;
use App\Domain\Services\Models\Service;
use App\Integrations\Contracts\MailGateway;
use App\Integrations\Contracts\WhatsAppGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Envía un recordatorio de vencimiento por correo (al cliente y a la dirección
 * interna) y por WhatsApp (si el cliente tiene número). Registra el envío en la
 * tabla reminders para no duplicarlo. Correo/WhatsApp usan adaptadores fake.
 */
class SendRenewalReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  string  $remindableType  'service' | 'domain' | 'addon'
     */
    public function __construct(
        public readonly string $remindableType,
        public readonly int $remindableId,
        public readonly int $daysBefore,
        public readonly string $dueDate,   // Y-m-d
    ) {}

    public function handle(MailGateway $mail, WhatsAppGateway $whatsapp): void
    {
        [$modelClass, $morphClass, $typeLabel] = $this->resolveType();
        if (! $modelClass) {
            return;
        }

        $model = $modelClass::find($this->remindableId);
        if (! $model) {
            return;
        }

        // Anti-duplicado: si ya existe el registro para esta entidad+ventana+fecha, no repetir.
        $already = Reminder::where('remindable_type', $morphClass)
            ->where('remindable_id', $this->remindableId)
            ->where('days_before', $this->daysBefore)
            ->whereDate('due_date', $this->dueDate)
            ->exists();

        if ($already) {
            return;
        }

        [$name, $clientName, $email, $whatsappNumber] = $this->resolveDetails($model);

        $subject = sprintf('Recordatorio: %s vence en %d día(s)', $typeLabel, $this->daysBefore);
        $body = implode("\n", [
            'Tu '.strtolower($typeLabel).' "'.$name.'" vence el '.$this->dueDate.'.',
            'Faltan '.$this->daysBefore.' día(s).',
            $clientName ? 'Cliente: '.$clientName : '',
        ]);

        // Correo al cliente (si tiene).
        if ($email) {
            $mail->send($email, $subject, $body, ['kind' => 'renewal', 'audience' => 'client']);
        }

        // Copia interna (con fallback por si la config no está disponible).
        $internal = config('renewals.notify_email') ?: 'renovaciones@wortev.com';
        $mail->send($internal, '[Interno] '.$subject, $body, [
            'kind' => 'renewal', 'audience' => 'internal',
        ]);

        // WhatsApp al cliente (si tiene número).
        if ($whatsappNumber) {
            $whatsapp->sendMessage($whatsappNumber, 'renewal_reminder', [
                'item' => $name,
                'due_date' => $this->dueDate,
                'days' => $this->daysBefore,
            ]);
        }

        // Aviso al ejecutivo de ventas responsable del servicio (por correo y,
        // si tiene, WhatsApp). El ejecutivo vive en el servicio: para dominios y
        // complementos se resuelve a través de su servicio.
        $rep = $this->resolveSalesRep($model);
        if ($rep) {
            $repBody = implode("\n", [
                'El '.strtolower($typeLabel).' "'.$name.'" '.($clientName ? 'de '.$clientName.' ' : '').'vence el '.$this->dueDate.'.',
                'Faltan '.$this->daysBefore.' día(s). Da seguimiento a la renovación.',
            ]);

            if ($rep->email) {
                $mail->send($rep->email, '[Ejecutivo] '.$subject, $repBody, [
                    'kind' => 'renewal', 'audience' => 'sales_rep',
                ]);
            }

            if ($rep->phone) {
                $whatsapp->sendMessage($rep->phone, 'renewal_reminder', [
                    'item' => $name,
                    'due_date' => $this->dueDate,
                    'days' => $this->daysBefore,
                ]);
            }
        }

        // Registrar para no reenviar.
        Reminder::create([
            'remindable_type' => $morphClass,
            'remindable_id' => $this->remindableId,
            'days_before' => $this->daysBefore,
            'due_date' => $this->dueDate,
            'sent_at' => Carbon::now(),
        ]);
    }

    /**
     * @return array{0: class-string|null, 1: string|null, 2: string}
     */
    private function resolveType(): array
    {
        return match ($this->remindableType) {
            'service' => [Service::class, (new Service)->getMorphClass(), 'Servicio'],
            'domain' => [Domain::class, (new Domain)->getMorphClass(), 'Dominio'],
            'addon' => [Addon::class, (new Addon)->getMorphClass(), 'Complemento'],
            default => [null, null, ''],
        };
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string, 3: ?string}
     */
    private function resolveDetails(object $model): array
    {
        if ($model instanceof Addon) {
            $client = $model->service?->client;
            $name = $model->name.' ('.$model->service?->name.')';
        } else {
            $client = $model->client;
            $name = $model->name;
        }

        $user = $client?->user;

        return [$name, $user?->name, $user?->email, $client?->whatsapp];
    }

    /**
     * Ejecutivo responsable del servicio que vence. El ejecutivo vive en el
     * servicio: un dominio o complemento lo hereda a través de su servicio.
     */
    private function resolveSalesRep(object $model): ?\App\Domain\Sales\Models\SalesRep
    {
        $service = match (true) {
            $model instanceof Service => $model,
            $model instanceof Addon => $model->service,
            $model instanceof Domain => $model->service,
            default => null,
        };

        return $service?->salesRep;
    }
}
