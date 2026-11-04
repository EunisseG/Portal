<?php

use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
| Rutas de API. Stateless (sin sesión). Aquí viven los webhooks entrantes de
| las integraciones externas.
*/

// Webhook genérico por proveedor. La verificación de firma e idempotencia se
// hace en el controlador; nunca se confía ciegamente en los datos externos.
Route::post('/webhooks/{provider}', WebhookController::class)->name('api.webhooks');
