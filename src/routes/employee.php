<?php

use App\Domain\Auth\Enums\RoleName;
use App\Livewire\Employee\Auth\EmployeeLogin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
| Portal interno de EMPLEADOS (/empleados)
| Acceso exclusivo para usuarios con rol "employee". El inicio de sesión es por
| número de empleado + PIN de un solo uso enviado al correo (sin contraseña).
| No está enlazado desde la web pública; la URL se comparte internamente.
*/

// Login del empleado (solo invitados; si ya hay sesión de empleado, al panel).
Route::get('/empleados/acceso', EmployeeLogin::class)
    ->middleware('guest')
    ->name('employee.login');

// Cierre de sesión del portal interno.
Route::post('/empleados/salir', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('employee.login');
})->middleware('auth')->name('employee.logout');

// Área autenticada del empleado. Sin "verified": el acceso ya es por PIN al
// correo, no por el flujo de verificación de email de Fortify.
Route::middleware(['auth', 'not.blocked', 'role:'.RoleName::Employee->value])
    ->prefix('empleados')
    ->name('employee.')
    ->group(function () {
        require __DIR__.'/employee_portal.php';
    });
