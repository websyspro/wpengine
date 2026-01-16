#!/usr/bin/env php
<?php

require __DIR__ . "/../../../autoload.php";

use Websyspro\WpEngine\Installer\WordPressInstaller;

$installer = new WordPressInstaller();
$installer->install();