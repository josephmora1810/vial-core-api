<?php

namespace App\Domain\ValueObjects;

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