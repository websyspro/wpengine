<?php

namespace Websyspro\WpEngine\Shareds;

use Websyspro\Commons\Collection;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Websyspro\Commons\Util;
use SplFileInfo;
use ZipArchive;

/**
 * WordpressPackage
 * 
 * Manages WordPress installation package operations.
 * Handles downloading, extracting, and installing WordPress core files from official releases.
 */
class WordpressPackage
{
  /** @var string Path to directory containing downloaded zip file */
  public string $sourceDirectoryZip;
  
  /** @var string Path to directory where zip will be extracted */
  public string $sourceDirectoryExtract;
  
  /** @var string Path to extracted WordPress directory */
  public string $sourceDirectoryExtractWordpress;
  
  /** @var string Path to target Core directory in project */
  public string $targetDirectorySrcCore;

  /**
   * Constructor
   * 
   * @param string $version WordPress version to install (e.g., "6.4.2")
   */
  public function __construct(
    public string $version
  ){}
  
  /**
   * Executes the complete WordPress installation process
   * 
   * Downloads, extracts, moves files, and creates configuration.
   * 
   * @return void
   */
  public function install(
  ): void {
    /** Setup source directories */
    $this->sourceDirectory();
    /** Setup target directories */
    $this->targetDirectory();
    /** Download WordPress zip */
    $this->downloadSource();
    /** Extract zip contents */
    $this->extractSource();
    /** Copy files to target */
    $this->moveToTarget(); 
    /** Generate wp-config.php */
    $this->createConfig();   
  }

  /**
   * Builds source directory path with optional subfolder
   * 
   * @param string|null $folder Optional subfolder name
   * @return string Full path to source directory
   */
  private function getSourceDirectory(
    string|null $folder = null
  ): string {
    /** Join temp directory with wordpress version and optional folder */
    return Util::join( 
      DIRECTORY_SEPARATOR, 
      [ sys_get_temp_dir(), "wordpress", $this->version, $folder ]
    );
  }

  /**
   * Creates multiple directories recursively
   * 
   * @param array $directorys Array of directory paths to create
   * @return void
   */
  private function mkdir(
    array $directorys
  ): void {
    /** Create each directory with full permissions recursively */
    Util::mapper(
      $directorys, 
      fn(string $directory) => (
        @mkdir($directory, 0777, true)
      )
    );
  }

  /**
   * Initializes and creates source directory structure
   * 
   * Sets up temporary directories for zip download and extraction.
   * 
   * @return void
   */
  private function sourceDirectory(
  ): void {
    /** Define all source directory paths */
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

    /** Create zip and extract directories */
    $this->mkdir( [ 
      $this->sourceDirectoryZip,
      $this->sourceDirectoryExtract
    ]);
  }

  /**
   * Builds target directory path with optional subfolder
   * 
   * @param string|null $folder Optional subfolder name
   * @return string Full path to target directory
   */
  private function getTargetDirectory(
    string|null $folder = null
  ): string {
    /** Extract base path before src directory */
    [ $target ] = explode( 
      "src", __DIR__
    );

    /** Clean path separators and join with src and optional folder */
    return Util::join(
      DIRECTORY_SEPARATOR, [
        preg_replace( [ 
          "#^[\\\\/]+#", "#[\\\\/]+$#", "#^/#", "#/$#"
        ], "", $target ), "src", $folder
      ]
    );
  }

  /**
   * Initializes and creates target directory structure
   * 
   * Sets up Core directory in project src folder.
   * 
   * @return void
   */
  private function targetDirectory(
  ): void {
    /** Define Core directory path */
    [ $this->targetDirectorySrcCore ] = [
      $this->getTargetDirectory( "Core" )
    ];
   
    /** Create Core directory */
    $this->mkdir( [ 
      $this->targetDirectorySrcCore
    ]);    
  }

  /**
   * Builds WordPress download URL for specified version
   * 
   * @return string URL to WordPress release zip file
   */
  private function sourceUrl(
  ): string {
    return "https://downloads.wordpress.org/release/wordpress-{$this->version}.zip";
  }
  
  /**
   * Gets full path to downloaded zip file
   * 
   * @return string Path to release.zip file
   */
  private function sourceZip(
  ): string {
    return Util::join( 
      DIRECTORY_SEPARATOR, [
        $this->sourceDirectoryZip, "realese.zip" 
      ]
    );
  }

  /**
   * Downloads WordPress release from official repository
   * 
   * @return void
   */
  private function downloadSource(
  ): void {
    /** Download WordPress zip from official repository */
    file_put_contents(
      $this->sourceZip(), 
      fopen( $this->sourceUrl(), "r" )
    );

    /** Output success message */
    fwrite( STDOUT, "  - Downloaded: \033[32m{$this->sourceZip()}\033[0m\n" );
  }

  /**
   * Extracts downloaded WordPress zip file
   * 
   * @return void
   */
  private function extractSource(
  ): void {
    /** Open and extract zip archive */
    $zipArchive = new ZipArchive();
    $zipArchive->open( $this->sourceZip());
    $zipArchive->extractTo( $this->sourceDirectoryExtract);
    $zipArchive->close();

    /** Output success message */
    fwrite( STDOUT, "  - Extracted: \033[32m{$this->sourceZip()}\033[0m\n" );
  }

  /**
   * Processes and copies a single file to target location
   * 
   * @param SplFileInfo $splFileInfo File information object
   * @return File New File instance representing the copied file
   */
  private function moveFile(
    SplFileInfo $splFileInfo,
    int $index = 0,
    int $all = 0
  ): File {
    /** Extract relative path from full path */
    [, $path ] = explode(
      Util::join(
      DIRECTORY_SEPARATOR, [ "extract", "wordpress" ]),
      $splFileInfo->getPath()
    );

    /** Output file being copied */
    fwrite( 
      STDOUT,
      Util::sprintFormat(
        "\033[2K\r  - Installing %s de %s file \033[32m%s\033[0m", [ 
          $index, $all, Util::join( 
            DIRECTORY_SEPARATOR,
            [ $path, $splFileInfo->getFilename() ]
          )
        ]
      )
    );

    /** Create File instance to copy file to target */
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

  /**
   * Recursively moves all WordPress files to target directory
   * 
   * @return void
   */
  private function moveToTarget(
    array $splFileInfoArr = []
  ): void {
    /** Create recursive iterator for WordPress directory */
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

    /** Iterate and copy each file */
    foreach($splFileInfoArr as $index =>  $splFileInfo){
      $this->moveFile(
        $splFileInfo, 
        $index + 1, 
        Util::sizeArray( $splFileInfoArr )
      );
    } 

    fwrite( STDOUT, "\033[2K\r" );
  }

  /**
   * Fetches WordPress security keys and salts from API
   * 
   * @param array $keys Array to store generated keys
   * @return array Associative array of security keys and salts
   */
  private function getSalt(
    array $keys = []
  ): array {
    /** Fetch and parse salt keys from WordPress API */
    preg_match_all(
      "#define\('([^']+)',\s*'([^']+)'\);#", 
      file_get_contents(
        "https://api.wordpress.org/secret-key/1.1/salt/"
      ), $matches
    );
    
    /** Format each key as define statement */
    foreach ($matches[1] as $index => $key) {
      $keys[$key] = Util::sprintFormat(
        "define( '%s', '%s' )", [
          $key, $matches[2][$index]
        ]
      );
    }    

    return $keys;
  }

  /**
   * Creates WordPress configuration file (wp-config.php)
   * 
   * Generates configuration with database settings, security keys, and custom paths.
   * 
   * @return void
   */
  private function createConfig(
  ): void {
    /** Get security keys and salts */
    $salt = $this->getSalt();
    /** Build configuration file content */
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

    /** Write configuration to wp-config.php */
    file_put_contents(
      __DIR__ . "/../Core/wp-config.php", 
      $createConfig->joinWithBreak()
    );

    /** Output success message */
    fwrite( STDOUT, "\033[0m  - Installation completed successfully.\n" );
  }
}