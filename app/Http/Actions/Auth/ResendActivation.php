<?php

namespace App\Http\Actions\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * Resend the account-activation email.
 *
 * "Activation" in this app rides on the password broker: for a
 * non-activated account, User::sendPasswordResetNotification() sends the
 * ActivateAccount notification, and redeeming the token sets
 * email_verified_at (NewPasswordController). Re-sending activation is
 * therefore just re-issuing a broker token — scoped to accounts that are
 * still inactive.
 *
 * The response is identical whether the address belongs to an inactive
 * account, an active account, or no account at all: this endpoint must
 * never become a registration/active-state oracle.
 */
class ResendActivation
{
    public function __invoke(Request $request): RedirectResponse
    {
        $email = $this->resolveEmail($request);

        if ($email !== null) {
            $user = User::where('email', mb_strtolower(trim($email)))->first();

            if ($user && ! $user->isActivated()) {
                Password::sendResetLink(['email' => $user->email]);
            }
        }

        return back()->with('status', __('auth.activation_sent_body'));
    }

    /**
     * Prefer the logged-in-but-inactive user, then an explicit form field
     * (the activation-sent page posts it), then the session.
     */
    private function resolveEmail(Request $request): ?string
    {
        $user = $request->user();
        if ($user && ! $user->isActivated()) {
            return $user->email;
        }

        $input = $request->input('email');
        if (is_string($input) && $input !== '') {
            $request->validate(['email' => ['email', 'max:255']]);

            return $input;
        }

        $sessionEmail = $request->session()->get('activation_email');

        return is_string($sessionEmail) && $sessionEmail !== '' ? $sessionEmail : null;
    }
}
