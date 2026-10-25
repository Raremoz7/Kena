<?php

namespace App\Rules;

use App\Support\EmailDomain;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** O domínio do e-mail precisa existir e receber e-mail (pega "hotmail.con"). */
class EmailDomainExists implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || EmailDomain::receivesMail($value)) {
            return;
        }

        $suggestion = EmailDomain::suggestion($value);
        $fail($suggestion !== null
            ? "Esse e-mail não existe. Você quis dizer {$suggestion}?"
            : 'Esse e-mail não existe: confira se o endereço depois do @ está certo.');
    }
}
