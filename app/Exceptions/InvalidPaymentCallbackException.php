<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Mokėjimų tiekėjo callback'as netinkamas: blogas parašas, svetimas projektas, nesutampa suma ar mokėjimas nerastas.
 * Tokiu atveju DB niekas nekeičiama, o tiekėjui atsakoma klaida (Paysera callback'ą kartos vėliau).
 */
class InvalidPaymentCallbackException extends RuntimeException {}
