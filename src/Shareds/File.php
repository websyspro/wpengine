<?php

namespace Websyspro\WpEngine\Shareds;

use Websyspro\Commons\Util;

/**
 * File
 * 
 * Handles file operations for WordPress installation.
 * Manages copying files from extracted WordPress source to target directory structure.
 */
class File
{
  /**
   * Constructor
   * 
   * Initializes file object and performs directory creation and file copying operations.
   * 
   * @param string $directoryExtractWordpress Source directory where WordPress was extracted
   * @param string $directorySrcCore Target core directory for WordPress files
   * @param string $directory Relative subdirectory path within the structure
   * @param string $filename Name of the file to be copied
   */
  public function __construct(
    public string $directoryExtractWordpress,
    public string $directorySrcCore,
    public string $directory,
    public string $filename
  ){
    /** Create target directory structure */
    $this->createDirectory();
    /** Copy file to target location */
    $this->createFile();
  }

  /**
   * Creates the target directory structure if it doesn't exist
   * 
   * @return void
   */
  private function createDirectory(
  ): void {
    /** Check if directory path is not empty */
    if( empty( $this->directory ) === false ){
      /** Create directory if it doesn't exist */
      if( file_exists( $this->directory ) === false ){
        @mkdir( Util::join(
          DIRECTORY_SEPARATOR, [ $this->directorySrcCore, $this->directory ]
        ), 0777, true);
      }
    }
  }

  /**
   * Gets the full source path of the file to be copied
   * 
   * @return string Full path to source file
   */
  private function getFrom(
  ): string {
    /** Build source path with or without subdirectory */
    return empty( $this->directory ) === false
      ? Util::join( DIRECTORY_SEPARATOR, [ $this->directoryExtractWordpress, $this->directory, $this->filename ])
      : Util::join( DIRECTORY_SEPARATOR, [ $this->directoryExtractWordpress, $this->filename ]);
  }

  /**
   * Gets the full target path where the file will be copied
   * 
   * @return string Full path to target file
   */
  private function getTo(
  ): string {
    /** Build target path with or without subdirectory */
    return empty( $this->directory ) === false
      ? Util::join( DIRECTORY_SEPARATOR, [ $this->directorySrcCore, $this->directory, $this->filename ])
      : Util::join( DIRECTORY_SEPARATOR, [ $this->directorySrcCore, $this->filename ]);
  }

  /**
   * Copies the file from source to target location
   * 
   * @return void
   */
  private function createFile(
  ): void {
    /** Copy file from source to target */
    copy( $this->getFrom(), $this->getTo());
  }
}