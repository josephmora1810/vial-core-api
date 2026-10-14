<?php
	spl_autoload_register(function ($class) {
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
