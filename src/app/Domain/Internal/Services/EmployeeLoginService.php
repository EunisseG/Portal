<?php

namespace App\Domain\Internal\Services;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Internal\Models\EmployeeLoginCode;
use App\Integrations\Contracts\MailGateway;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Autenticación de empleados por número de empleado + PIN de un solo uso
 * enviado por correo. No usa contraseña: el empleado solo necesita su número y
 * el PIN que recibe en su buzón. El PIN caduca y se invalida al usarse.
 */
class EmployeeLoginService
{
    /** Minutos de validez del PIN. */
    public const TTL_MINUTES = 10;

    /** Máximo de intentos de validación por código antes de invalidarlo. */
    public const MAX_ATTEMPTS = 5;

    public function __construct(private readonly MailGateway $mail) {}

    /**
     * Resuelve al empleado por su número. Devuelve null si no existe, no es
     * empleado o está bloqueado (no se revela cuál es el motivo al usuario).
     */
    public function findEmployee(string $employeeNumber): ?User
    {
        $employeeNumber = trim($employeeNumber);
        if ($employeeNumber === '') {
            return null;
        }

        $user = User::where('employee_number', $employeeNumber)->first();

        if (! $user || ! $user->hasRole(RoleName::Employee->value) || $user->isBlocked()) {
            return null;
        }

        return $user;
    }

    /**
     * Genera un PIN para el empleado, lo guarda hasheado y lo envía por correo.
     * Invalida cualquier PIN anterior sin usar. Devuelve el PIN en claro solo
     * para pruebas (en producción el valor solo viaja por correo).
     */
    public function issueCode(User $employee): string
    {
        // Invalida PINs previos no usados.
        EmployeeLoginCode::where('user_id', $employee->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        EmployeeLoginCode::create([
            'user_id' => $employee->id,
            'code_hash' => Hash::make($pin),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $this->mail->send(
            $employee->email,
            'Tu código de acceso al portal de empleados',
            implode("\n", [
                'Hola '.$employee->name.',',
                '',
                'Tu código de acceso es: '.$pin,
                'Caduca en '.self::TTL_MINUTES.' minutos y solo puede usarse una vez.',
                '',
                'Si no solicitaste este acceso, ignora este mensaje.',
            ]),
            ['kind' => 'employee_login', 'audience' => 'employee'],
        );

        return $pin;
    }

    /**
     * Valida el PIN para el empleado. Devuelve true si es correcto, vigente y
     * no usado; en ese caso lo marca como usado. Controla intentos.
     */
    public function verifyCode(User $employee, string $pin): bool
    {
        $pin = trim($pin);

        $code = EmployeeLoginCode::where('user_id', $employee->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $code) {
            return false;
        }

        // Demasiados intentos: invalida el código.
        if ($code->attempts >= self::MAX_ATTEMPTS) {
            $code->update(['used_at' => now()]);

            return false;
        }

        if (! Hash::check($pin, $code->code_hash)) {
            $code->increment('attempts');

            return false;
        }

        $code->update(['used_at' => now()]);

        return true;
    }
}
