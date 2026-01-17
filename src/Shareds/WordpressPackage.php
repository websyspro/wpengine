<?php

namespace Websyspro\WpEngine\Shareds;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Websyspro\Commons\Util;
use ZipArchive;

/**
 * WordpressInstall
 * 
 * Handles WordPress installation file structure parsing and loading.
 * Recursively fetches and organizes files and directories from a WordPress installation URL.
 */
class WordpressPackage
{
  public string $sourceDirectoryZip;
  public string $sourceDirectoryExtract;
  public string $sourceDirectoryExtractWordpress;
  public string $targetDirectorySrcCore;

  public function __construct(
    public string $version
  ){}
  
  public function install(
  ): void {
    $this->sourceDirectory();
    $this->targetDirectory();
    $this->downloadSource();
    $this->extractSource();
    $this->moveToTarget();    
  }

  private function getSourceDirectory(
    string|null $folder = null
  ): string {
    return Util::join( 
      DIRECTORY_SEPARATOR, 
      [ sys_get_temp_dir(), "wordpress", $this->version, $folder ]
    );
  }

  private function mkdir(
    array $directorys
  ): void {
    Util::mapper(
      $directorys, 
      fn(string $directory) => (
        @mkdir($directory, 0777, true)
      )
    );
  }

  private function sourceDirectory(
  ): void {
    [ $this->sourceDirectoryZip, 
      $this->sourceDirectoryExtract,
      $this->sourceDirectoryExtractWordpress ] = [
      $this->getSourceDirectory( "zip" ), 
      $this->getSourceDirectory( "extract" ),
      $this->getSourceDirectory( Util::join(
        DIRECTORY_SEPARATOR, 
        [ "extract", "wordpress" ]
      ))
    ];

    $this->mkdir( [ 
      $this->sourceDirectoryZip,
      $this->sourceDirectoryExtract
    ]);
  }

  private function getTargetDirectory(
    string|null $folder = null
  ): string {
    [ $target ] = explode( 
      "src", __DIR__
    );

    return Util::join(
      DIRECTORY_SEPARATOR, [
        preg_replace( [ 
          "#^[\\\\/]+#", "#[\\\\/]+$#", "#^/#", "#/$#"
        ], "", $target ), "src", $folder
      ]
    );
  }

  private function targetDirectory(
  ): void {
    [ $this->targetDirectorySrcCore ] = [
      $this->getTargetDirectory( "Core" )
    ];
   
    $this->mkdir( [ 
      $this->targetDirectorySrcCore
    ]);    
  }

  private function sourceUrl(
  ): string {
    return "https://downloads.wordpress.org/release/wordpress-{$this->version}.zip";
  }
  
  private function sourceZip(
  ): string {
    return Util::join( 
      DIRECTORY_SEPARATOR, [
        $this->sourceDirectoryZip, "realese.zip" 
      ]
    );
  }

  private function downloadSource(
  ): void {
    file_put_contents(
      $this->sourceZip(), 
      fopen( $this->sourceUrl(), "r" )
    );
  }

  private function extractSource(
  ): void {
    $zipArchive = new ZipArchive();
    $zipArchive->open( $this->sourceZip());
    $zipArchive->extractTo( $this->sourceDirectoryExtract);
    $zipArchive->close();
  }

  private function moveFile(
    SplFileInfo $splFileInfo
  ): File {
    [, $path ] = explode(
      Util::join(
      DIRECTORY_SEPARATOR, [ "extract", "wordpress" ]),
      $splFileInfo->getPath()
    );

    return new File(
      $this->sourceDirectoryExtractWordpress,
      $this->targetDirectorySrcCore,
      preg_replace(
        [ "#^[\\\\/]+#", "#[\\\\/]+$#", "#^/#", "#/$#"  ], 
        "", $path
      ), 
      $splFileInfo->getFilename()
    );
  }

  private function moveToTarget(
  ): void {
    $splFileInfoIterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator(
        $this->sourceDirectoryExtractWordpress,
        RecursiveDirectoryIterator::SKIP_DOTS
      )
    );

    foreach($splFileInfoIterator as $splFileInfo){
      if( $splFileInfo->isFile() ){
        $this->moveFile( $splFileInfo );
      }
    } 
  }
}