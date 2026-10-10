<?php

namespace App\Controllers;

use App\Core\Response;
use App\Core\Request;
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

	public function bodyTest(Request $request){
		$data = $request->all();

		Response::json(
			data: $data,
			code: 200,
			status: 'success',
			message: 'Datos recibidos correctamente'
		);
	}

	public function showAnUser(Request $_, int $id){
		Response::json(
			data: [
				'id' => $id,
				'name' => 'John Doe',
				'email' => 'john.doe@example.com'
			],
			code: 200,
			status: 'success',
			message: 'Usuario encontrado correctamente'
		);
	}
}
