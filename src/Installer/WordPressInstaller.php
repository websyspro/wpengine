<?php

use Websyspro\WpEngine\Shareds\WordPressCrawler;
use Websyspro\WpEngine\Shareds\TreeFilter;
use Websyspro\WpEngine\Shareds\TreeDownloader;

$version = '6.9';
$url     = "https://core.svn.wordpress.org/tags/{$version}/";
$target  = getcwd() . '/vendor/wpcore';

$crawler = new WordPressCrawler();
$tree    = $crawler->crawl($url);

$core    = TreeFilter::filter($tree);

TreeDownloader::download($core, $target);