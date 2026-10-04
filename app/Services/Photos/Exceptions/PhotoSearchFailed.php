<?php

namespace App\Services\Photos\Exceptions;

use RuntimeException;

/**
 * Paieška nepavyko (API klaida, netikėtas atsakymas). Kita frazė ar kitas šaltinis gali pavykti.
 */
class PhotoSearchFailed extends RuntimeException {}
