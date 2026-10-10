<?php

namespace App\Core;

use Closure;

interface MiddlewareInterface{
	public function handle(Request $request, Closure $next);
}
