<?php

namespace App\Ai;

use RuntimeException;

/**
 * An AI request failed; the message is an English translation key shown to the user.
 */
class AiException extends RuntimeException {}
