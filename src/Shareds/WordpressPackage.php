<?php

namespace Websyspro\WpEngine\Shareds;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Websyspro\Commons\Collection;
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
    $this->createConfig();   
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

    fwrite( STDOUT, "Downloaded: {$this->sourceZip()}\n" );
  }

  private function extractSource(
  ): void {
    $zipArchive = new ZipArchive();
    $zipArchive->open( $this->sourceZip());
    $zipArchive->extractTo( $this->sourceDirectoryExtract);
    $zipArchive->close();

    fwrite( STDOUT, "Extracted: {$this->sourceZip()}\n" );
  }

  private function moveFile(
    SplFileInfo $splFileInfo
  ): File {
    [, $path ] = explode(
      Util::join(
      DIRECTORY_SEPARATOR, [ "extract", "wordpress" ]),
      $splFileInfo->getPath()
    );

    fwrite( STDOUT, "Copy {$splFileInfo->getFilename()}\n" );

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

  private function getSalt(
    array $keys = []
  ): array {
    preg_match_all(
      "#define\('([^']+)',\s*'([^']+)'\);#", 
      file_get_contents(
        "https://api.wordpress.org/secret-key/1.1/salt/"
      ), $matches
    );
    
    foreach ($matches[1] as $index => $key) {
      $keys[$key] = Util::sprintFormat(
        "define( '%s', '%s' )", [
          $key, $matches[2][$index]
        ]
      );
    }    

    return $keys;
  }

  private function createConfig(
  ): void {
    $salt = $this->getSalt();
    $createConfig = new Collection(
      [
        "<?php",
        "",
        "/*",
        " * Database settings",
        " **/",
        "define( 'DB_NAME', 'composer' );",
        "define( 'DB_USER', 'root' );",
        "define( 'DB_PASSWORD', 'qazwsx' );",
        "define( 'DB_HOST', 'localhost:3308' );",
        "define( 'DB_CHARSET', 'utf8mb4' );",
        "define( 'DB_COLLATE', '' );",
        "",
        "/*",
        " * Database settings",
        " **/",
        "{$salt['AUTH_KEY']};",
        "{$salt['SECURE_AUTH_KEY']};",
        "{$salt['LOGGED_IN_KEY']};",
        "{$salt['NONCE_KEY']};",
        "{$salt['AUTH_SALT']};",
        "{$salt['SECURE_AUTH_SALT']};",
        "{$salt['LOGGED_IN_SALT']};",
        "{$salt['NONCE_SALT']};",
        "",
        "/*",
        " * WordPress database table prefix.",
        " **/",
        "\$table_prefix = 'wp_';",
        "",
        "/*",
        " * WordPress debugging mode.",
        " **/",
        "define( 'WP_DEBUG', false );",
        "",
        "/*",
        " * Absolute path to the WordPress directory.",
        " **/",
        "if( defined( 'ABSPATH' ) === false ){",
        "\tdefine( 'ABSPATH', __DIR__ . '/' );",
        "}",
        "",
        "/*",
        " * Paths customizados.",
        " **/",
        "define( 'WP_CONTENT_DIR', ROUTE_ROOT . '/src' );",
        "define( 'WP_CONTENT_URL', 'http://' . \$_SERVER['HTTP_HOST'] );",
        "define( 'WP_SITEURL', 'http://' . \$_SERVER['HTTP_HOST'] );",
        "define( 'WP_HOME', 'http://' . \$_SERVER['HTTP_HOST'] );",
        "",
        "/*",
        " * Includes Plugings.",
        " **/",
        "require_once ABSPATH . 'wp-includes/plugin.php';",
        "",
        "/*",
        " * Includes Plugings.",
        " **/",
        "if( php_sapi_name() === 'cli-server' ){",
        "\tadd_filter( 'got_url_rewrite', '__return_true' );",
        "}",
        "",
        "/*",
        " * Sets up WordPress vars and included files..",
        " **/",
        "require_once ABSPATH . 'wp-settings.php';"
      ]
    );

    file_put_contents(
      __DIR__ . "/../Core/wp-config.php", 
      $createConfig->joinWithBreak()
    );

    fwrite( STDOUT, "Config created\n" );
  }
}