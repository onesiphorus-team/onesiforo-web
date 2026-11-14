<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when no JW.org media matches the criteria of a random session.
 */
class NoRandomMediaAvailableException extends RuntimeException {}
