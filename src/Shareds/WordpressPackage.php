<?php

namespace Websyspro\WpEngine\Shareds;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Websyspro\Commons\Util;
use SplFileInfo;
use Websyspro\Utils\Collection;
use ZipArchive;
use stdClass;
use function is_array;
use function sprintf;
use function sizeof;

class WordpressPackage
{
  public float  $versionDefault = 6.9;
  public string $sourceDirectoryZip; 
  public string $sourceDirectoryExtract;
  public string $sourceDirectoryExtractWordpress;
  public string $targetDirectorySrcCore;
  public string $version;

  public function __construct(
  ){}
  
  public function install(
  ): void {
    $this->sourceConfigReader();
    $this->sourceDirectory();
    $this->targetDirectory();
    $this->downloadSource();
    $this->extractSource();
    $this->moveToTarget(); 
    $this->createConfig();   
  }

  private function getLastVersion(
  ): float {
    $versionCheck = file_get_contents(
      "https://api.wordpress.org/core/version-check/1.7/"
    );

    if( $versionCheck !== false ){
      $versionCheckJson = json_decode(
        $versionCheck
      );

      if( $versionCheckJson instanceof stdClass === false ){
        return $this->versionDefault;
      }

      if( isset( $versionCheckJson->offers ) && is_array( $versionCheckJson->offers )){
        [ $offer ] = $versionCheckJson->offers;
        
        if( $offer instanceof stdClass === false ){
          return $this->versionDefault;
        }

        return $offer->version; 
      }
    }

    return $this->versionDefault;
  }

  private function sourceConfigReader(
  ): void {
    $composerConfig = __DIR__ . "/../../../../../composer.json";

    if( file_exists( $composerConfig )){
      $composerConfig = json_decode(
        file_get_contents(
          $composerConfig
        )
      );

      $composerVersion = isset( $composerConfig->extra ) 
                      && isset( $composerConfig->extra->wordpress ) 
                      && isset( $composerConfig->extra->wordpress->version );

      $this->version = $composerVersion 
        ? $composerConfig->extra->wordpress->version
        : $this->getLastVersion();
    }
  }

  private function getSourceDirectory(
    string|null $folder = null
  ): string {
    return implode( 
      DIRECTORY_SEPARATOR, 
      [ sys_get_temp_dir(), "wordpress", $this->version, $folder ]
    );
  }

  private function mkdir(
    array $directorys
  ): void {
    array_map( fn(string $directory) => @mkdir($directory, 0777, true), $directorys );
  }


  private function sourceDirectory(
  ): void {
    [ $this->sourceDirectoryZip, 
      $this->sourceDirectoryExtract,
      $this->sourceDirectoryExtractWordpress ] = [
      $this->getSourceDirectory( "zip" ), 
      $this->getSourceDirectory( "extract" ),
      $this->getSourceDirectory( implode(
        DIRECTORY_SEPARATOR, 
        [ "extract", "wordpress" ]
      ))
    ];

    $this->mkdir([ 
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

    return implode(
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
   
    $this->mkdir([ 
      $this->targetDirectorySrcCore
    ]);    
  }

  private function sourceUrl(
  ): string {
    return sprintf(
      "https://downloads.wordpress.org/release/wordpress-%s-no-content.zip", $this->version
    );
  }
  
  private function sourceZip(
  ): string {
    return implode( 
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

    fwrite( STDOUT, "  - Downloaded: \033[32m{$this->sourceZip()}\033[0m\n" );
  }

  private function extractSource(
  ): void {
    $zipArchive = new ZipArchive();
    $zipArchive->open( $this->sourceZip());
    $zipArchive->extractTo( $this->sourceDirectoryExtract);
    $zipArchive->close();

    fwrite( STDOUT, "  - Extracted: \033[32m{$this->sourceZip()}\033[0m\n" );
  }

  private function moveFile(
    SplFileInfo $splFileInfo,
    int $index = 0,
    int $all = 0
  ): File {
    [, $path ] = explode(
      implode( DIRECTORY_SEPARATOR, [ "extract", "wordpress" ]), $splFileInfo->getPath()
    );

    $perc = bcmul(
      bcdiv( $index, $all, 4 ), 
      100, 2
    );

    fwrite( 
      STDOUT,
      sprintf( "\033[2K\r  - Installing %s de %s file %s: \033[32m%s\033[0m",  
        $index, $all, "{$perc}%", $splFileInfo->getFilename()
      )
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
    array $splFileInfoArr = []
  ): void {
    $splFileInfoIterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator(
        $this->sourceDirectoryExtractWordpress,
        RecursiveDirectoryIterator::SKIP_DOTS
      )
    );

    foreach($splFileInfoIterator as $splFileInfo){
      if( $splFileInfo->isFile() ){
        $splFileInfoArr[] = $splFileInfo;
      } 
    }

    foreach($splFileInfoArr as $index => $splFileInfo){
      $this->moveFile(
        $splFileInfo, 
        $index + 1, 
        sizeof( $splFileInfoArr )
      );
    } 

    fwrite( STDOUT, "\033[2K\r" );
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
      $keys[$key] = sprintf( "define( '%s', '%s' )", $key, $matches[2][$index]);
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
        "define( 'DB_NAME', getenv( 'DB_NAME' ));",
        "define( 'DB_USER', getenv( 'DB_USER' ));",
        "define( 'DB_PASSWORD', getenv( 'DB_PASSWORD' ));",
        "define( 'DB_HOST', getenv( 'DB_HOST' ));",
        "define( 'DB_CHARSET', getenv( 'DB_CHARSET' ));",
        "define( 'DB_COLLATE', getenv( 'DB_COLLATE' ));",
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
        " * Includes AfterSetupThems.",
        " **/",        
        "add_action( 'after_setup_theme', function () {",
        "\tadd_theme_support( 'post-thumbnails' );",
        "});",        
        "",
        "/*",
        " * Sets up WordPress vars and included files..",
        " **/",
        "require_once ABSPATH . 'wp-settings.php';"
      ]
    );

    file_put_contents(
      __DIR__ . "/../Core/wp-config.php", 
      $createConfig->join( PHP_EOL )
    );

    fwrite( STDOUT, "\033[0m  - WordPress configuration file created (\033[32mwp-config.php\033[0m).\n" );
    fwrite( STDOUT, "\033[0m  - Installation completed successfully.\n" );
  }
}