<?php

namespace App\Core;
use App\Domain\Users\Entities\User;

class Request {

	private ?User $user = null;
	
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
		
		/**
		 * Ahora prescindimos de sanitizar los datos, 
		 * eso lo haremos con values object, 
		 * que es una clase que nos permite validar y sanitizar los datos de manera más eficiente
		 */

		//Con esto capturamos los parametros de la url (?id=3)
		if(!empty($_GET)) $data = array_merge($data, $_GET);
		//Con esto capturamos los parametros de un formulario (name=juan)
		if(!empty($_POST)) $data = array_merge($data, $_POST);

		//Con esto capturamos los json que se pasen por el body
		$jsonBody = file_get_contents('php://input'); // leemos el cuerpo bruto de la aplicación
		$jsonData  = json_decode($jsonBody, true); // lo volvemos un array de php

		//Si es un json válido, lo fusionamos con los demas datos
		if(json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) $data = array_merge($data, $jsonData);	

		return $data;
	}

	public function body(): array
	{
		return $this->all();
	}

	/**
	 * Obtiene información de un archivo cargado ($_FILES)
	 */
	public function file(string $key): ?array
	{
		return $_FILES[$key] ?? null;
	}

	/**
	 * Comprueba si un archivo fue cargado exitosamente sin errores
	 */
	public function hasFile(string $key): bool
	{
		return isset($_FILES[$key]) && (int)($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
	}

	/**
	 * Obtiene el mensaje de error correspondiente si la subida falló
	 */
	public function fileErrorMessage(string $key): ?string
	{
		if (!isset($_FILES[$key])) {
			return "No se recibió ningún archivo en el campo \"{$key}\".";
		}

		$error = (int)($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE);
		return match ($error) {
			UPLOAD_ERR_OK => null,
			UPLOAD_ERR_INI_SIZE => sprintf(
				'El archivo adjunto supera el tamaño máximo permitido por el servidor (upload_max_filesize: %s).',
				ini_get('upload_max_filesize') ?: '2M'
			),
			UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido en el formulario.',
			UPLOAD_ERR_PARTIAL   => 'El archivo se subió solo parcialmente.',
			UPLOAD_ERR_NO_FILE   => "No se adjuntó ningún archivo en el campo \"{$key}\".",
			UPLOAD_ERR_NO_TMP_DIR => 'Falta el directorio temporal en el servidor.',
			UPLOAD_ERR_CANT_WRITE => 'No se pudo guardar el archivo en el disco.',
			UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP detuvo la carga del archivo.',
			default => "Error en la subida del archivo (código de error {$error})."
		};
	}

	public function setUser(User $user): self
	{
		$this->user = $user;
		return $this;
	}

	public function user(): ?User
	{
		return $this->user;
	}

	public function getUser(): ?User
	{
		return $this->user;
	}

	public function bearerToken(): ?string
	{
		$header = $_SERVER['HTTP_AUTHORIZATION'] 
			?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] 
			?? '';

		if (empty($header) && function_exists('apache_request_headers')) {
			$headers = apache_request_headers();
			$header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
		}

		if (str_starts_with($header, 'Bearer ')) {
			return substr($header, 7);
		}

		return null;
	}

	public function header(string $key, ?string $default = null): ?string
	{
		$formattedKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
		if (isset($_SERVER[$formattedKey])) {
			return $_SERVER[$formattedKey];
		}

		if (strcasecmp($key, 'Content-Type') === 0 && isset($_SERVER['CONTENT_TYPE'])) {
			return $_SERVER['CONTENT_TYPE'];
		}

		if (strcasecmp($key, 'Content-Length') === 0 && isset($_SERVER['CONTENT_LENGTH'])) {
			return $_SERVER['CONTENT_LENGTH'];
		}

		if (function_exists('apache_request_headers')) {
			$headers = apache_request_headers();
			foreach ($headers as $k => $v) {
				if (strcasecmp($k, $key) === 0) {
					return $v;
				}
			}
		}

		return $default;
	}

	public function isJson(): bool
	{
		$contentType = $this->header('Content-Type', '');
		return str_contains($contentType, 'application/json');
	}

	public function wantsJson(): bool
	{
		$accept = $this->header('Accept', '');
		return empty($accept) || str_contains($accept, 'application/json') || str_contains($accept, '*/*');
	}
}
