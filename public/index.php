<?php

use Pimcore\Bootstrap;
use Pimcore\Tool;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

Bootstrap::setProjectRoot();

return function (Request $request, array $context) {

    Tool::setCurrentRequest($request);

    Bootstrap::bootstrap();
    $kernel = Bootstrap::kernel();

    Tool::setCurrentRequest(null);

    return $kernel;
};
