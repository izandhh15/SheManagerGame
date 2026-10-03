<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivationEvent;
use App\Models\InviteCode;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Modules\Season\Services\ActivationTracker;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     *
     * Registration is open: anyone can sign up. An invite code (via ?invite=)
     * still works to pre-fill name/email when present.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $invite = InviteCode::findByCode($request->query('invite'));

        $name = null;
        $email = null;
        if ($invite) {
            $name = WaitlistEntry::whereEmail($invite->email)->first()?->name;
            $email = $invite->email ?? null;
        }

        return view('auth.register-career-mode', [
            'inviteCode' => $invite ? $request->query('invite') : null,
            'betaMode' => config('beta.enabled'),
            'name' => $name,
            'email' => $email,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * Registration is open: the invite code is optional. When a valid code is
     * supplied it is consumed and its grants apply; otherwise the new account
     * gets full access (career + tournament + national modes).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function storeCareerModeRegistration(Request $request): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'invite_code' => ['nullable', 'string'],
        ];

        $request->validate($rules);

        $invite = $request->filled('invite_code')
            ? InviteCode::findByCode($request->input('invite_code'))
            : null;

        if ($request->filled('invite_code') && (! $invite || ! $invite->isValidForEmail($request->input('email')))) {
            return back()->withErrors([
                'invite_code' => __('beta.invalid_invite'),
            ])->withInput();
        }

        $user = null;
        try {
            // Create the user and consume the invite atomically. The invite
            // is re-validated under a row lock: without this, two concurrent
            // submits could both pass the check above and burn a single-use
            // code twice (TOCTOU).
            $user = DB::transaction(function () use ($request, $invite) {
                $lockedInvite = $invite
                    ? InviteCode::whereKey($invite->id)->lockForUpdate()->first()
                    : null;

                if ($invite && (! $lockedInvite || ! $lockedInvite->isValidForEmail($request->input('email')))) {
                    throw new InviteConsumedException();
                }

                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                ]);

                // Access flags are not mass assignable — see User::$fillable.
                // Open registration grants full access; a valid invite code narrows
                // it to whatever the code grants.
                $user->forceFill([
                    'email_verified_at' => now(),
                    'has_career_access' => $lockedInvite ? $lockedInvite->grants_career : true,
                    'has_tournament_access' => $lockedInvite ? $lockedInvite->grants_tournament : true,
                ])->save();

                $lockedInvite?->consume();

                return $user;
            });
        } catch (InviteConsumedException) {
            return back()->withErrors([
                'invite_code' => __('beta.invalid_invite'),
            ])->withInput();
        }

        event(new Registered($user));

        app(ActivationTracker::class)->record($user->id, ActivationEvent::EVENT_REGISTERED);

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}

/**
 * Thrown inside the registration transaction when the invite code that
 * passed the pre-check is no longer valid once the row lock is held
 * (consumed by a concurrent submit). Caught by the controller to render
 * the same validation error as the pre-check.
 */
class InviteConsumedException extends \RuntimeException {}
