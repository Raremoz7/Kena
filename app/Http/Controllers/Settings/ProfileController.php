<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\EmailVerificationCodeService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user('web') instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'emailCodeUrl' => route('email-code.send'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, EmailVerificationCodeService $codes): RedirectResponse
    {
        $user = $request->user('web');
        $data = $request->validated();
        $data['email'] = EmailVerificationCodeService::normalize($data['email']);

        $user->fill(['name' => $data['name']]);
        if (EmailVerificationCodeService::normalize($user->email) !== $data['email']) {
            $user->fill(['email' => $data['email']]);
        }

        // E-mail novo só com o código enviado a ele: sem isso, alguém registra o
        // e-mail de outra pessoa e herda o login Google e as transferências dela.
        if ($user->isDirty('email')) {
            if (! $codes->verify($data['email'], (string) ($data['code'] ?? ''))) {
                throw ValidationException::withMessages([
                    'code' => 'Para trocar o e-mail, digite o código que enviamos para o novo endereço.',
                ]);
            }

            // email_verified_at não é fillable: o código acabou de confirmar o endereço.
            $user->forceFill(['email_verified_at' => now()]);
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user('web');

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
