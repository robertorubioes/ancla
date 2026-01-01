<?php

namespace App\Http\Responses;

use App\Enums\UserRole;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        $user = auth()->user();
        
        // Superadmin goes to admin panel
        if ($user->role === UserRole::SUPER_ADMIN || $user->role?->value === 'super_admin') {
            return redirect()->intended('/admin/tenants');
        }
        
        // Regular users go to signing processes dashboard
        return redirect()->intended('/signing-processes');
    }
}
