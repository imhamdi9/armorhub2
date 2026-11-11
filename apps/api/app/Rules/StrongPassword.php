<?php

namespace App\Rules;

use Illuminate\Validation\Rules\Password;

/** The one password policy used everywhere (signup, reset, staff accounts). */
class StrongPassword
{
    /** @return array<int, mixed> */
    public static function rules(bool $confirmed = false): array
    {
        $rule = Password::min(10)->letters()->numbers();

        // Reject passwords seen in known data breaches (queries haveibeenpwned with a k-anonymity range).
        if (config('platform.check_pwned_passwords')) {
            $rule = $rule->uncompromised();
        }

        return array_filter(['required', 'string', $confirmed ? 'confirmed' : null, $rule]);
    }
}
