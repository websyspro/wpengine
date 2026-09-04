<?php

namespace Websyspro\WpEngine\Shareds;

use Websyspro\Commons\Util;

class File
{
  public function __construct(
    public string $directoryExtractWordpress,
    public string $directorySrcCore,
    public string $directory,
    public string $filename
  ){
    $this->createDirectory();
    $this->createFile();
  }

  private function createDirectory(
  ): void {
    if( empty( $this->directory ) === false ){
      if( file_exists( $this->directory ) === false ){
        @mkdir( Util::join(
          DIRECTORY_SEPARATOR, [ $this->directorySrcCore, $this->directory ]
        ), 0777, true);
      }
    }
  }

  private function getFrom(
  ): string {
    return empty( $this->directory ) === false
      ? Util::join( DIRECTORY_SEPARATOR, [ $this->directoryExtractWordpress, $this->directory, $this->filename ])
      : Util::join( DIRECTORY_SEPARATOR, [ $this->directoryExtractWordpress, $this->filename ]);
  }

  private function getTo(
  ): string {
    return empty( $this->directory ) === false
      ? Util::join( DIRECTORY_SEPARATOR, [ $this->directorySrcCore, $this->directory, $this->filename ])
      : Util::join( DIRECTORY_SEPARATOR, [ $this->directorySrcCore, $this->filename ]);
  }

  private function createFile(
  ): void {
    copy( $this->getFrom(), $this->getTo());
  }
}