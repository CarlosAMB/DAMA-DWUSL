<?php

namespace App\Services;

use RuntimeException;

/** La cuenta de Google no esta conectada o el acceso fue revocado. */
class GoogleAuthException extends RuntimeException
{
}
