<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorios de vencimiento: se ejecuta a diario por la mañana.
Schedule::command('reminders:send')->dailyAt('08:00');

// Suspende complementos recurrentes vencidos (caducan por su propio ciclo).
Schedule::command('addons:expire')->dailyAt('02:00');

// Expira órdenes de pago en línea no pagadas (plazo de 5 días).
Schedule::command('payment-orders:expire')->dailyAt('03:00');

// Genera órdenes de pago de renovación para pedidos próximos a vencer. Corre
// después de la expiración para reemplazar las órdenes que caducaron.
Schedule::command('payment-orders:generate-renewals')->dailyAt('03:30')->withoutOverlapping();

// Revisa incumplimientos de SLA en tickets activos y avisa al equipo. Corre
// seguido para detectar vencimientos poco después de que ocurran.
Schedule::command('sla:check')->everyFifteenMinutes()->withoutOverlapping();

// Auditoría semanal de dependencias (Composer + npm): detecta paquetes
// vulnerables/obsoletos y avisa al equipo si aparece algo urgente nuevo.
Schedule::command('dependencies:check')->weeklyOn(1, '04:00')->withoutOverlapping();
