<?php
	spl_autoload_register(function ($class) {
		$classmap = [
			'FPDF'   => __DIR__ . '/src/Infrastructure/Pdf/Fpdf/fpdf.php',
			'QRcode' => __DIR__ . '/src/Infrastructure/Pdf/PhpQrCode/phpqrcode.php',
		];

		if (isset($classmap[$class]) && file_exists($classmap[$class])) {
			require_once $classmap[$class];
			return;
		}

		$prefixes = [
			'App\\' => __DIR__ . '/src/',
			'Tests\\' => __DIR__ . '/tests/',
		];

		foreach ($prefixes as $prefix => $baseDir) {
			if (str_starts_with($class, $prefix)) {
				$relativeClass = substr($class, strlen($prefix));
				$file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
				if (file_exists($file)) {
					require $file;
					return;
				}
			}
		}
	});
