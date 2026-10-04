<?php

namespace App\Core;

class Request {
	
	// funcion que se trae el metodo de la peticion http
	public function method(){
		return $_SERVER['REQUEST_METHOD'] ?? 'GET';
	}

	// función que se trae la url de la peticion http
	public function uri() {
		$uri = $_SERVER['REQUEST_URI'] ?? '/';
		return parse_url($uri, PHP_URL_PATH);
	}

	// SIrve para pedir un dato específico, y, en caso de que falte, podemos poner un datos por default
	public function input(string $key, $default = null){
		$data = $this->all();
		return $data[$key] ?? $default;
	}

	//Sirve para traer todos los datos, tanto de parametros de url como de jsonBody
	public function all(){
		$data = [];
		
		//Con esto capturamos los parametros de la url (?id=3)
		if(!empty($_GET)) {
			foreach($_GET as $key => $value){
				$data[$key] = filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS);
			}
		}

		if(!empty($_POST)){
			foreach($_POST as $key => $value){
				$data[$key] = filter_input(INPUT_POST, $key, FILTER_SANITIZE_SPECIAL_CHARS);
			}
		}

		//Con esto capturamos los json que se pasen por el body
		$jsonBody = file_get_contents('php://input'); // leemos el cuerpo bruto de la aplicación
		$jsonData  = json_decode($jsonBody, true); // lo volvemos un array de php

		//Si es un jsoválido, lo fusionamos con los demas datos
		if(json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) $data = array_merge($data, $jsonData);	

		return $data;
	}
}
