<?php

namespace Websyspro\WpEngine\Shareds;

use Websyspro\WpEngine\Interfaces\IDirectory;
use Websyspro\WpEngine\Interfaces\IFile;

final class DirectoryNode implements IDirectory
{
  /** @var array<IDirectory|IFile> */
  private array $children = [];

  public function __construct(
    private string $name
  ){}

  public function addChild(
    IDirectory|IFile $node
  ): void {
    $this->children[] = $node;
  }

  public function getName(
  ): string {
    return $this->name;
  }

  public function getChildren(
  ): array {
    return $this->children;
  }
}
