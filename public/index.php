<?php
declare(strict_types=1);

/**
 * iERA App - front controller.
 * Every HTTP request is rewritten here by public/.htaccess.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\App;

App::boot()->run();
