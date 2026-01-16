<?php

namespace Websyspro\WpEngine\Shareds;

use Websyspro\WpEngine\Interfaces\IDirectory;
use Websyspro\WpEngine\Interfaces\IFile;

final class TreeDownloader
{
  public static function download(
    IDirectory $node,
    string $basePath
  ): void {
    $path = rtrim($basePath, '/') . '/' . $node->getName();

    if ($node->getName() !== '' && !is_dir($path)) {
        mkdir($path, 0777, true);
    }

    foreach ($node->getChildren() as $child) {
      if ($child instanceof IDirectory) {
          self::download($child, $path);
      }

      if ($child instanceof IFile) {
          file_put_contents(
              $path . '/' . $child->getName(),
              file_get_contents($child->getUrl())
          );
      }
    }
  }
}
