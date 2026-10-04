<?php

namespace App\Services\Photos\Exceptions;

use RuntimeException;

/**
 * Atsisiųstas failas netinka: ne paveikslėlis, per didelis, per mažas arba serveris grąžino klaidą.
 */
class InvalidPhoto extends RuntimeException {}
