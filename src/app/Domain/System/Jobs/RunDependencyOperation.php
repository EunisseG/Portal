<?php

namespace App\Domain\System\Jobs;

use App\Domain\System\Services\DependencyUpdateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Ejecuta en segundo plano una operación sobre dependencias (actualización o
 * restauración). Se pasa solo el id: tras restaurar la base de datos, un
 * modelo serializado podría ya no coincidir.
 *
 * Un solo intento: una actualización nunca debe repetirse sola. Si la cola
 * reentrega el trabajo, el servicio lo ignora (reclamo atómico del estado).
 */
class RunDependencyOperation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public int $timeout;

    public function __construct(public readonly int $operationId)
    {
        $this->timeout = (int) config('dependencies.job_timeout', 1800);
    }

    public function handle(DependencyUpdateService $service): void
    {
        $service->perform($this->operationId);
    }

    /** La cola la dio por fallida (p. ej. tiempo agotado): se deja constancia. */
    public function failed(?Throwable $exception): void
    {
        app(DependencyUpdateService::class)->markInterrupted(
            $this->operationId,
            $exception?->getMessage() ?? 'sin detalle',
        );
    }
}
