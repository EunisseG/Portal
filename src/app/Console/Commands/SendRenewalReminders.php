<?php

namespace App\Console\Commands;

use App\Domain\Notifications\Jobs\SendRenewalReminder;
use App\Domain\Notifications\Models\Reminder;
use App\Domain\Notifications\Services\UpcomingRenewals;
use Illuminate\Console\Command;

class SendRenewalReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Encola recordatorios de vencimiento (servicios, dominios y complementos) según los umbrales configurados.';

    public function handle(UpcomingRenewals $renewals): int
    {
        /** @var list<int> $thresholds */
        $thresholds = config('renewals.thresholds', [30, 15, 7, 1]);

        $queued = 0;

        foreach ($thresholds as $days) {
            $items = $renewals->exactlyInDays((int) $days);

            foreach ($items as $item) {
                if ($item->clientId === null) {
                    continue; // sin cliente asociado, no hay a quién avisar.
                }

                $morphClass = $item->model->getMorphClass();
                $dueDate = $item->dueDate->toDateString();

                // Evita encolar si ya se registró este recordatorio.
                $exists = Reminder::where('remindable_type', $morphClass)
                    ->where('remindable_id', $item->model->id)
                    ->where('days_before', (int) $days)
                    ->whereDate('due_date', $dueDate)
                    ->exists();

                if ($exists) {
                    continue;
                }

                SendRenewalReminder::dispatch(
                    $item->type,
                    (int) $item->model->id,
                    (int) $days,
                    $dueDate,
                );
                $queued++;
            }
        }

        $this->info("Recordatorios encolados: {$queued}.");

        return self::SUCCESS;
    }
}
