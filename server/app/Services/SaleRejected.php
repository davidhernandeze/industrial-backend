<?php

namespace App\Services;

use RuntimeException;

/**
 * A sale that cannot go through, with a message for the person at the till.
 */
class SaleRejected extends RuntimeException {}
