#!/usr/bin/env php
<?php

require __DIR__ . "/../../../autoload.php";

use Websyspro\WpEngine\Shareds\WordpressPackage;

$version = "6.7";

$wordpressInstall = new WordpressPackage( $version );
$wordpressInstall->install();