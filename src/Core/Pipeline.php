<?php

namespace App\Core;

use Closure;
use App\Core\Request;

class Pipeline{
	protected array $pipes = [];
	protected Request $request;

	public function send(Request $request) : self {
		$this->request = $request;
		return $this;
	}

	public function through(array $pipes): self {
		$this->pipes = $pipes;
		return $this;
	}

	public function then(Closure $destination){
		$pipeline = array_reduce(
			array_reverse($this->pipes),
			$this->carry(),
			$destination
		);

		return $pipeline($this->request);
	}

	protected function carry(): Closure {
		return fn($next, $pipe) => 
			fn($request) => 
				($middleware = \is_object($pipe) ? $pipe : new $pipe())
					->handle($request, $next);
	}
}
