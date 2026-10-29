<?php

namespace App\Core;

use Exception;

class DotEnv
{
	/** @var array<string, string> */
	protected array $variable = [];

	public function __construct( protected string $path) 
	{
		if (!file_exists($path)) {
			throw new Exception("Archivo .env no se encuentra");
		}
	}

	public function load() : void
	{
		//Revisamos si el archivo se puede leer
		if (!is_readable($this->path)) throw new Exception("El archivo {$this->path} no se puede Leer");

		// Leemos ese archivo (ignorando los saltos de linea y las lineas vacías)
		$lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		
		foreach ($lines as $line) {
			// si la linea es un comentario, la dejamos pasar
			if (str_starts_with(trim($line), '#')) continue;

			if (str_contains($line, '=')) {
				// separamos el nombre de la variable de entorno con su valor
				[$name, $value] = explode('=', $line, 2);

				// eliminamos los espacios en blanco y otros caracteres indeseados
				$name = trim($name);
				$value = trim(trim($value), "\x00..\x1F\"");
				$this->variable[$name] = $value;

				// los guardamos en putenv() y en $_ENV para poder ser llamados por getenv() , $_SERVER y $_ENV
				if (!\array_key_exists($name, $_SERVER) && !\array_key_exists($name, $_ENV)) {
					putenv(\sprintf('%s=%s', $name, $value));
					$_ENV[$name] = $value;
					$_SERVER[$name] = $value;
				}
			}
		}

		//Definimos la zona horaria de la aplicacion (Pero preferiblemente que sea America/Caracas)
		$timezone = $_ENV['APP_TIMEZONE'] ?? 'America/Caracas';
		if (\is_string($timezone) && $timezone !== '') {
			@date_default_timezone_set($timezone);
		}
	}
}
