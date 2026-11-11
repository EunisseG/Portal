<?php

namespace App\Rules;

use App\Support\RichText;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida el cuerpo de un mensaje del editor enriquecido: el texto visible no
 * puede estar vacío (p. ej. "<p><br></p>") ni pasar del máximo de caracteres.
 */
class RichTextBody implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('El mensaje no es válido.');

            return;
        }

        if (strlen($value) > RichText::MAX_HTML_LENGTH) {
            $fail('El mensaje es demasiado largo.');

            return;
        }

        $text = RichText::plainText($value);

        if ($text === '') {
            $fail('Escribe un mensaje.');

            return;
        }

        if (mb_strlen($text) > RichText::MAX_TEXT_LENGTH) {
            $fail('El mensaje no puede tener más de '.RichText::MAX_TEXT_LENGTH.' caracteres.');
        }
    }
}
