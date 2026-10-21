<?php

namespace App\Domain\Auth\ValueObjects;

use InvalidArgumentException;

readonly class Password
{
    private const string PATTERN = '((?=.*[A-Z])(?=.*[a-z])(?=.*\d).{7,21})';

    private function __construct(private string $hashedValue)
    {
    }

    // 1. Creador para cuando el usuario se está registrando (Texto Plano)
    public static function fromPlainText(string $plainPassword): self
    {
        if (!preg_match(self::PATTERN, $plainPassword)) {
            throw new InvalidArgumentException('La contraseña debe tener entre 7 y 21 caracteres, incluyendo una letra minúscula, una mayúscula y un número.');
        }
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        
        return new self($hash);
    }

    /**
     * Genera una contraseña aleatoria criptográficamente segura que cumple con el patrón exigido
     * (entre 7 y 21 caracteres, con al menos una mayúscula, una minúscula y un número).
     */
    public static function generateRandomPlainText(int $length = 10): string
    {
        $length = max(8, min(18, $length));
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $digits = '23456789';
        $all = $upper . $lower . $digits;

        $chars = [
            $upper[random_int(0, strlen($upper) - 1)],
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
        ];

        for ($i = count($chars); $i < $length; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        // Mezclar con Fisher-Yates criptográfico
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    // 2. Creador para cuando traemos el dato de la base de datos (Ya está hasheado)
    public static function fromHash(string $hashedPassword): self
    {
        return new self($hashedPassword);
    }

     // Método para verificar si el texto ingresado en el login hace match con el hash
    public function verify(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->hashedValue);
    }

    public function getValue(): string
    {
        return $this->hashedValue;
    }

    public function equals(Password $other): bool
    {
        return $this->hashedValue === $other->getValue();
    }
}