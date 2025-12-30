<?php

namespace App\Http\Controllers;

use App\Mail\UserWelcomeMail;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    /**
     * Show invitation acceptance form.
     */
    public function show(string $token)
    {
        $invitation = UserInvitation::findValidByToken($token);

        if (! $invitation) {
            return view('invitation.invalid');
        }

        return view('invitation.accept', [
            'invitation' => $invitation,
            'token' => $token,
        ]);
    }

    /**
     * Accept invitation and create user account.
     */
    public function accept(Request $request, string $token)
    {
        $invitation = UserInvitation::findValidByToken($token);

        if (! $invitation) {
            return redirect()->route('invitation.show', $token)
                ->with('error', 'This invitation is invalid or has expired.');
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()],
        ]);

        // Check if user already exists
        $existingUser = User::where('tenant_id', $invitation->tenant_id)
            ->where('email', $invitation->email)
            ->first();

        if ($existingUser) {
            return redirect()->route('invitation.show', $token)
                ->with('error', 'A user with this email already exists.');
        }

        // Create user
        $user = User::create([
            'tenant_id' => $invitation->tenant_id,
            'name' => $invitation->name,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'status' => 'active',
            'password' => Hash::make($validated['password']),
        ]);

        // Mark invitation as accepted
        $invitation->markAsAccepted();

        // Send welcome email
        Mail::to($user->email)->send(
            new UserWelcomeMail($user, $invitation->tenant)
        );

        // Log user in
        auth()->login($user);

        // Update last login
        $user->updateLastLogin();

        return redirect()->route('dashboard')
            ->with('message', 'Welcome! Your account has been created successfully.');
    }
}
