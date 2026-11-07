<?php

namespace App\Households;

use RuntimeException;

/**
 * The AccountManager did not answer (connection error, timeout) or answered with an unexpected
 * status. The app keeps its last mirrored state.
 */
class KontoUnavailable extends RuntimeException {}
