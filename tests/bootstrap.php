<?php

declare(strict_types=1);

/*
 * This file is part of the doctrine-oci8-extended package.
 *
 * (c) Jason Hofer <jason.hofer@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Dotenv\Dotenv;

$autoloader = require __DIR__ . '/../vendor/autoload.php';

$dotenv = new Dotenv(true);
$dotenv->loadEnv(__DIR__ . '/../.env');
