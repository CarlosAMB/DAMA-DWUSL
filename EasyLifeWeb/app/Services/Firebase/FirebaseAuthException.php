<?php

namespace App\Services\Firebase;

/** El ID token de Firebase no es valido (firma, proyecto o vigencia). */
class FirebaseAuthException extends \RuntimeException {}
