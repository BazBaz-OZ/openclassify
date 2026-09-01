<?php

declare(strict_types=1);

namespace Modules\User\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(): RedirectResponse
    {
        return redirect()->route('panel.profile.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updateProfile', [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users')->ignore($request->user()->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'show_phone' => ['nullable', 'boolean'],
            'show_email' => ['nullable', 'boolean'],
        ]);

        $phone = trim((string) ($validated['phone'] ?? ''));
        $showPhone = $request->boolean('show_phone');
        $showEmail = $request->boolean('show_email');

        unset($validated['phone'], $validated['show_phone'], $validated['show_email']);

        $request->user()->fill($validated);

        $emailChanged = $request->user()->isDirty('email');

        if ($emailChanged) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        if ($emailChanged) {
            $request->user()->sendEmailVerificationNotification();
        }

        $request->user()->profile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'phone' => $phone !== '' ? $phone : null,
                'show_phone' => $showPhone,
                'show_email' => $showEmail,
            ],
        );

        return redirect()->route('panel.profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
