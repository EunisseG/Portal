<?php

namespace App\Livewire\Employee\Auth;

use App\Domain\Internal\Services\EmployeeLoginService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Inicio de sesión del portal interno de empleados en dos pasos:
 *   1) El empleado introduce su número de empleado -> se le envía un PIN al correo.
 *   2) Introduce el PIN -> se valida y se inicia sesión.
 *
 * Por seguridad no se revela si un número de empleado existe o no: siempre se
 * muestra el paso del PIN. Está limitado por tasa para evitar abuso.
 */
#[Layout('layouts.employee-guest')]
class EmployeeLogin extends Component
{
    /** Paso actual: 'number' o 'code'. */
    public string $step = 'number';

    public string $employee_number = '';

    public string $pin = '';

    /** Id del empleado resuelto en el paso 1 (si corresponde). */
    public ?int $employeeId = null;

    public function requestCode(EmployeeLoginService $service): void
    {
        $this->validate([
            'employee_number' => ['required', 'string', 'max:50'],
        ], [], ['employee_number' => 'número de empleado']);

        // Límite por número + IP para evitar enumeración/abuso.
        $key = 'emp-login:'.$this->employee_number.'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('employee_number', 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.');

            return;
        }
        RateLimiter::hit($key, 600);

        $employee = $service->findEmployee($this->employee_number);
        if ($employee) {
            $this->employeeId = $employee->id;
            $service->issueCode($employee);
        } else {
            // No revelamos que no existe: avanzamos igual al paso del PIN.
            $this->employeeId = null;
        }

        $this->step = 'code';
        $this->pin = '';
        session()->flash('status', 'Si el número corresponde a un empleado, te enviamos un código a tu correo.');
    }

    public function verify(EmployeeLoginService $service): mixed
    {
        $this->validate([
            'pin' => ['required', 'string', 'max:10'],
        ], [], ['pin' => 'código']);

        $employee = $this->employeeId ? \App\Models\User::find($this->employeeId) : null;

        if (! $employee || ! $service->verifyCode($employee, $this->pin)) {
            $this->addError('pin', 'Código incorrecto o vencido. Solicita uno nuevo.');

            return null;
        }

        Auth::login($employee);

        // Regenera la sesión para evitar fijación de sesión (si hay sesión en
        // la petición actual).
        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        return $this->redirectRoute('employee.dashboard', navigate: false);
    }

    /** Vuelve al paso 1 para pedir otro código. */
    public function restart(): void
    {
        $this->reset('step', 'pin', 'employeeId');
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.employee.auth.employee-login');
    }
}
