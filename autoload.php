<?php
	spl_autoload_register(function ($class) {
		//Define el prefijo del namespace y el directorio base
		$prefix = 'App\\';
		$baseDir = __DIR__ . '/src/';

		//Comprueba si la clase usa el prefijo de namespace (usa string position *strpos*)
		if(strpos($class, $prefix) === 0) {
			//Eliminar el prefijo 'App\' para obtener el nombre de clase relativo
			$relativeClass = substr($class, strlen($prefix));

			//Convertimos los separadores del namespace (\) a separadores del directorio (/)
			//Además anteponemos 'src/' y añadimos .php al final para construir la rta del archivo
			$file = $baseDir . str_replace('\\','/',$relativeClass) . '.php';

			// si existe el archivo, lo requerimos
			if(file_exists($file)) require $file;
		}
	});
