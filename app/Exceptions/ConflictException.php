<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A write that conflicts with current state (record still in use, asset already
 * borrowed...). JSON callers get 409; Inertia pages go back with an error toast
 * (rendered in bootstrap/app.php). The message is shown to the user.
 */
class ConflictException extends RuntimeException {}
