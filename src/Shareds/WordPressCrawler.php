<?php

namespace Websyspro\WpEngine\Shareds;

use RuntimeException;

final class WordPressCrawler
{
  private array $visited = [];

  public function crawl(
    string $url,
    string $name = ""
  ): DirectoryNode {
    if( isset($this->visited[ $url ])){
      return new DirectoryNode($name);
    }

    $this->visited[$url] = true;

    $html = file_get_contents($url, false,  stream_context_create([
      'http' => [
          'method'  => 'GET',
          'header'  => implode("\r\n", [
              'User-Agent: websyspro-wp-engine/1.0',
              'Accept: text/html'
          ]),
          'timeout' => 30,
      ]
    ]));
    
    if( $html === false ){
      throw new RuntimeException("Falha ao acessar {$url}");
    }

    $dir = new DirectoryNode($name);

    preg_match_all('/href="([^"]+)"/i', $html, $matches);

    foreach( $matches[1] as $href ){
      if ($href === '../') {
          continue;
      }

      $fullUrl = rtrim($url, '/') . '/' . $href;

      // Diretório
      if (str_ends_with($href, '/')) {
          $childName = rtrim($href, '/');
          $dir->addChild(
              $this->crawl($fullUrl, $childName)
          );
      }
      // Arquivo
      else {
          $dir->addChild(
              new FileNode($href, $fullUrl)
          );
      }
    }

    return $dir;
  }
}
