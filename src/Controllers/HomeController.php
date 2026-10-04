<?php

namespace App\Controllers;

use App\Core\Response;
use Exception;

class HomeController{

	public function index() {
		Response::json(
			data: [
				'servicio' => 'API',
				'version' => 1.0
			],
			code: 200,
			status: 'success',
			message: 'El Servidor Funciona Correctamente'
		);
	}

	public function teapot(){
		throw new Exception("I´m a teapot, I refuse making coffee",418);
	}
}
