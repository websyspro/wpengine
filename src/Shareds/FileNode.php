<?php

namespace Websyspro\WpEngine\Shareds;

use Websyspro\WpEngine\Interfaces\IFile;

final class FileNode implements IFile
{
  public function __construct(
    private string $name,
    private string $url
  ){}

  public function getName(
  ): string {
    return $this->name;
  }

  public function getUrl(
  ): string {
      return $this->url;
  }
}
