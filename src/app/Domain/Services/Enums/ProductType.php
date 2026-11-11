<?php

namespace App\Domain\Services\Enums;

/**
 * "Funciona como": comportamiento fijo de un producto (no editable). Define
 * qué hace el sistema con él; la organización del catálogo va en Categorías.
 *  - Hosting: solo nace de un plan (Sistema → Servidores / Planes); lleva
 *    servidor y dominio en el pedido.
 *  - Dominio: el título es la extensión (.com, .mx) y se ofrece en el apartado
 *    de dominio del pedido.
 *  - Correo / Licencia / Otro: productos sin comportamiento especial.
 */
enum ProductType: string
{
    case Hosting = 'hosting';
    case Domain = 'domain';
    case Email = 'email';
    case License = 'license';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Hosting => 'Hosting',
            self::Domain => 'Dominio',
            self::Email => 'Correo',
            self::License => 'Licencia',
            self::Custom => 'Otro',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Opciones para dar de alta a mano: todas menos Hosting (que sale de los
     * planes).
     *
     * @return array<string, string>
     */
    public static function manualOptions(): array
    {
        $options = self::options();
        unset($options[self::Hosting->value]);

        return $options;
    }
}
