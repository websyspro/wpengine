<?php

namespace Websyspro\WpEngine\Shareds;

use Websyspro\WpEngine\Interfaces\IDirectory;
use Websyspro\WpEngine\Interfaces\IFile;

final class TreeFilter
{
  private const ALLOWED_DIRS = [
    "wp-admin",
    "wp-includes",
  ];

  public static function filter(
    IDirectory $node
  ): DirectoryNode {
    $filtered = new DirectoryNode($node->getName());

    foreach ($node->getChildren() as $child) {
      // Diretório
      if( $child instanceof IDirectory ){

        if (!in_array($child->getName(), self::ALLOWED_DIRS, true)) {
            continue;
        }

        $filtered->addChild(
          self::filter($child)
        );
      }

      // Arquivo
      if ($child instanceof IFile) {
        if (
          str_ends_with($child->getName(), '.php') ||
          $child->getName() === "license.txt"
        ) {
          $filtered->addChild( $child );
        }
      }
    }

    return $filtered;
  }
}
