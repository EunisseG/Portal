<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Clients\Services\SendAccountSetupLink;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Solicitud de enlace de recuperación de contraseña que acepta tanto el correo
 * principal como el secundario del usuario. El enlace se envía a la dirección
 * que la persona escribió (así funciona aunque haya perdido acceso al correo
 * principal). Siempre responde con un mensaje genérico para no revelar si un
 * correo existe o no.
 */
class RecoveryLinkController extends Controller
{
    public function store(Request $request, SendAccountSetupLink $setupLink): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $typed = $data['email'];

        // Límite de intentos sencillo por correo+IP se delega al middleware de
        // rutas si está configurado; aquí nos enfocamos en no filtrar datos.
        $user = User::where('email', $typed)
            ->orWhere('secondary_email', $typed)
            ->first();

        if ($user) {
            // El token se liga al correo de login; el correo se entrega a la
            // dirección escrita (principal o secundaria).
            $setupLink->send($user, toEmail: $typed, isReset: true);
        }

        return back()->with('status', 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña.');
    }
}
