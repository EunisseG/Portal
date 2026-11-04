<?php

use App\Livewire\Employee\Tickets\TicketCreate as EmployeeTicketCreate;
use App\Livewire\Employee\Tickets\TicketIndex as EmployeeTicketIndex;
use App\Livewire\Employee\Tickets\TicketShow as EmployeeTicketShow;
use Illuminate\Support\Facades\Route;

/*
| Rutas internas del portal de empleados (ya dentro del grupo con prefijo
| "empleados" y nombre "employee."). El empleado solo gestiona sus tickets
| internos.
*/

// Panel: listado de sus tickets internos (es también el dashboard).
Route::get('/', EmployeeTicketIndex::class)->name('dashboard');
Route::get('/tickets/nuevo', EmployeeTicketCreate::class)->name('tickets.create');
Route::get('/tickets/{ticket}', EmployeeTicketShow::class)->name('tickets.show');
