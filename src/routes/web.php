<?php

use App\Http\Controllers\Auth\RecoveryLinkController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\InvoiceDownloadController;
use App\Http\Controllers\PaymentProofDownloadController;
use App\Http\Controllers\PaymentReceiptDownloadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TicketAttachmentController;
use Illuminate\Support\Facades\Route;

/*
| Rutas públicas
*/
Route::view('/', 'welcome')->name('home');

// Solicitud de enlace de recuperación que acepta correo principal o secundario
// (el envío por defecto de Fortify solo busca por el correo principal).
Route::post('/recuperar-contrasena', [RecoveryLinkController::class, 'store'])
    ->middleware('guest')
    ->name('password.email.recovery');

/*
| Rutas autenticadas comunes (cualquier rol)
| Fortify registra automáticamente las rutas de autenticación
| (login, register, password reset, email verification).
*/
Route::middleware(['auth', 'verified', 'not.blocked'])->group(function () {
    // Punto de entrada tras login: redirige al área según rol.
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard');

    // Perfil (compartido por ambas áreas).
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    // Descarga de archivos de factura (protegida por policy: admin o cliente dueño).
    // Se registran ambos nombres para que las vistas admin y cliente los usen.
    Route::get('/facturas/{invoice}/pdf', [InvoiceDownloadController::class, 'pdf'])->name('admin.invoices.download');
    Route::get('/facturas/{invoice}/xml', [InvoiceDownloadController::class, 'xml'])->name('admin.invoices.download.xml');
    Route::get('/mis-facturas/{invoice}/pdf', [InvoiceDownloadController::class, 'pdf'])->name('client.invoices.download');
    Route::get('/mis-facturas/{invoice}/xml', [InvoiceDownloadController::class, 'xml'])->name('client.invoices.download.xml');

    // Recibo de una orden de pago en PDF (estilo recibo). El controlador
    // autoriza admin o cliente dueño; se registran ambos nombres para las
    // vistas admin y cliente.
    Route::get('/ordenes-pago/{paymentOrder}/recibo-pdf', PaymentReceiptDownloadController::class)->name('admin.payment-orders.receipt.pdf');
    Route::get('/mis-ordenes-pago/{paymentOrder}/recibo-pdf', PaymentReceiptDownloadController::class)->name('client.payment-orders.receipt.pdf');

    // Descarga de adjuntos de tickets (protegida por policy: staff o cliente dueño).
    Route::get('/tickets/adjuntos/{attachment}', TicketAttachmentController::class)->name('tickets.attachment');

    // Descarga de comprobantes de pago (protegida por policy: manager o cliente dueño).
    // Se registran ambos nombres para que las vistas admin y cliente los usen.
    Route::get('/mis-comprobantes/{paymentProof}/archivo', PaymentProofDownloadController::class)->name('client.payment-proofs.download');
    Route::get('/comprobantes/{paymentProof}/archivo', PaymentProofDownloadController::class)->name('admin.payment-proofs.download');
});

require __DIR__.'/client.php';
require __DIR__.'/admin.php';
require __DIR__.'/employee.php';
