<?php

namespace App\Database;

use PDO;
use PDOException;
use Exception;

class Database {
	private PDO $connection;

	public function __construct() {
		$host = $_ENV['DATABASE_HOST'] ?? '127.0.0.1';
		$port = $_ENV['DATABASE_PORT'] ?? '3306';
		$db = $_ENV['DATABASE_NAME'] ?? '';
		$user = $_ENV['DATABASE_USER'] ?? 'root';
		$pass = $_ENV['DATABASE_PASSWORD'] ?? '';
	
		// el dns (data source name) para postgres/mysql/mariadb
		$dns = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

		try {
			$options = [
				PDO::ATTR_ERRMODE             => PDO::ERRMODE_EXCEPTION,  // Que los Errores lanzen Excepciones
				PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,       // Que las respuestas sean arreglos ['id' => 1]
				PDO::ATTR_EMULATE_PREPARES    => false                  // Seguridad contra inyección sql
			];

			$this->connection = new PDO($dns, $user, $pass, $options);


		}catch(PDOException $e){
			throw new Exception("Error al Intentar Conectar a la Base de Datos: {$e->getMessage}", 500);
		}
	}

	public function getConnection() : PDO {
		return $this->connection;
	}
}
