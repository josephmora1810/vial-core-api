<?php

namespace App\Infrastructure\Database;

use Exception;

class Redis{
    /** @var resource|false */
    private $socket;

    public function __construct() {
        $host = !empty($_ENV['REDIS_HOST']) ? $_ENV['REDIS_HOST'] : '127.0.0.1';
        $port = !empty($_ENV['REDIS_PORT']) ? (int)$_ENV['REDIS_PORT'] : 6379;
        $timeout = !empty($_ENV['REDIS_TIMEOUT']) ? (float)$_ENV['REDIS_TIMEOUT'] : 1.0;

        $this->socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (!$this->socket) {
            throw new Exception("No se pudo conectar a Redis/Memurai en {$host}:{$port} -> $errstr ($errno)");
        }
    }

    /**
     * @return resource|false
     */
    public function getConnection() {
        return $this->socket;
    }

    public function execute(...$args){
        $cmd = '*' . \count($args) . "\r\n";
        foreach($args as $arg){
            $cmd .= '$' . \strlen($arg) . "\r\n" . $arg . "\r\n";
        }

        fwrite($this->socket,$cmd);
        return $this->readResponse();
    }

    private function readResponse(){
        $linea = fgets($this->socket);
        if($linea === false) return throw new Exception("Error al leer la respuesta de Redis");
        $tipo = $linea[0];
        $contenido = substr($linea, 1, -2);
        switch ($tipo){
            case '+':
                return $contenido;
            case '-':
                throw new Exception("Error de Redis: $contenido");
            case ':':
                return (int)$contenido;
            case '$':
                $longitud = (int)$contenido;
                if ($longitud === -1) return null; 
                $datos = '';
                while (\strlen($datos) < $longitud + 2) {
                    $datos .= fread($this->socket, $longitud + 2 - \strlen($datos));
                }
                return substr($datos, 0, -2);   
            case '*':
                $cantidad = (int)$contenido;
                $resultado = [];
                for ($i = 0; $i < $cantidad; $i++) {
                    $resultado[] = $this->readResponse();
                }
                return $resultado;  
            default:
                throw new Exception("Error al leer la respuesta de Redis");
        }
    }
    
    public function disconnect(){
        if ($this->socket) fclose($this->socket);
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}