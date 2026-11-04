<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        // Los clientes gestionan su perfil desde el apartado "Mi Cuenta".
        if (! $user->isAdminArea()) {
            return redirect()->route('client.account.profile');
        }

        return view('profile.edit', [
            'user' => $user,
        ]);
    }
}
