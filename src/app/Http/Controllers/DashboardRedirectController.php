<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Punto de entrada tras iniciar sesión. Envía al usuario al área que
 * corresponde según su rol.
 */
class DashboardRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->isAdminArea()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user && $user->isEmployee()) {
            return redirect()->route('employee.dashboard');
        }

        return redirect()->route('client.dashboard');
    }
}
