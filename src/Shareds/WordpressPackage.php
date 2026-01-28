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
  public string $version;
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
   * Initializes a new WordpressPackage instance.
   * The version will be determined from composer.json during installation.
   * 
   * @param string $version WordPress version to install (e.g., "6.4.2")
   */
  public function __construct(
  ){
    /* Empty constructor - version is set during install process */
  }
  
  /**
   * Executes the complete WordPress installation process
   * 
   * This method orchestrates the entire WordPress installation workflow:
   * 1. Reads version from composer.json
   * 2. Sets up temporary and target directories
   * 3. Downloads WordPress from official repository
   * 4. Extracts the downloaded archive
   * 5. Moves files to the project structure
   * 6. Creates WordPress configuration
   * 
   * @return void
   */
  public function install(
  ): void {
    /* Read WordPress version from composer.json configuration */
    $this->sourceConfigReader();
    
    /* Setup source directories for download and extraction */
    $this->sourceDirectory();
    
    /* Setup target directories in project structure */
    $this->targetDirectory();
    
    /* Download WordPress zip from official repository */
    $this->downloadSource();
    
    /* Extract zip contents to temporary directory */
    $this->extractSource();
    
    /* Copy files from temporary to target location */
    $this->moveToTarget(); 
    
    /* Generate wp-config.php configuration file */
    $this->createConfig();   
  }

  /**
   * Reads WordPress version from composer.json configuration
   * 
   * Locates the composer.json file in the project root and extracts
   * the WordPress version from extra.wordpress.version property.
   * Falls back to version 6.7 if not specified.
   * 
   * @return void
   */
  private function sourceConfigReader(
  ): void {
    /* Build path to composer.json file */
    $composerConfig = __DIR__ . "/../../../../../composer.json";

    /* Check if composer.json exists and parse it */
    if( file_exists( $composerConfig )){
      /* Decode JSON configuration file */
      $composerConfig = json_decode(
        file_get_contents(
          $composerConfig
        )
      );

      /* Check if WordPress version is defined in extra.wordpress.version */
      $composerVersion = isset( $composerConfig->extra ) 
                      && isset( $composerConfig->extra->wordpress ) 
                      && isset( $composerConfig->extra->wordpress->version );

      /* Set version from config or use default 6.7 */
      $this->version = $composerVersion 
        ? $composerConfig->extra->wordpress->version
        : 6.9;
    }
  }

  /**
   * Builds source directory path with optional subfolder
   * 
   * Constructs a path in the system temporary directory for WordPress files.
   * Pattern: /tmp/wordpress/{version}/{folder}
   * 
   * @param string|null $folder Optional subfolder name (e.g., "zip", "extract")
   * @return string Full path to source directory
   */
  private function getSourceDirectory(
    string|null $folder = null
  ): string {
    /* Join system temp directory with wordpress version folder and optional subfolder */
    return Util::join( 
      DIRECTORY_SEPARATOR, 
      [ sys_get_temp_dir(), "wordpress", $this->version, $folder ]
    );
  }

  /**
   * Creates multiple directories recursively
   * 
   * Iterates through an array of directory paths and creates each one
   * with full permissions (0777) and recursive flag enabled.
   * Suppresses errors with @ operator.
   * 
   * @param array $directorys Array of directory paths to create
   * @return void
   */
  private function mkdir(
    array $directorys
  ): void {
    /* Create each directory with full permissions (0777) recursively, suppress errors */
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
   * Sets up temporary directories for WordPress installation:
   * - sourceDirectoryZip: Where the downloaded zip file is stored
   * - sourceDirectoryExtract: Where the zip is extracted
   * - sourceDirectoryExtractWordpress: The extracted wordpress folder
   * 
   * @return void
   */
  private function sourceDirectory(
  ): void {
    /* Define all source directory paths using destructuring assignment */
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

    /* Create zip and extract directories in the temporary location */
    $this->mkdir( [ 
      $this->sourceDirectoryZip,
      $this->sourceDirectoryExtract
    ]);
  }

  /**
   * Builds target directory path with optional subfolder
   * 
   * Extracts the project root by splitting on 'src' directory,
   * then reconstructs the path with src and optional subfolder.
   * Cleans leading/trailing slashes and backslashes.
   * 
   * @param string|null $folder Optional subfolder name (e.g., "Core")
   * @return string Full path to target directory
   */
  private function getTargetDirectory(
    string|null $folder = null
  ): string {
    /* Extract base path before src directory from current file location */
    [ $target ] = explode( 
      "src", __DIR__
    );

    /* Clean leading/trailing slashes and backslashes, then join with src and optional folder */
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
   * Sets up the Core directory in the project's src folder where
   * WordPress core files will be installed.
   * 
   * @return void
   */
  private function targetDirectory(
  ): void {
    /* Define Core directory path in project structure */
    [ $this->targetDirectorySrcCore ] = [
      $this->getTargetDirectory( "Core" )
    ];
   
    /* Create Core directory if it doesn't exist */
    $this->mkdir( [ 
      $this->targetDirectorySrcCore
    ]);    
  }

  /**
   * Builds WordPress download URL for specified version
   * 
   * Constructs the official WordPress download URL using the version
   * specified in the instance property.
   * 
   * @return string URL to WordPress release zip file
   */
  private function sourceUrl(
  ): string {
    /* Return official WordPress download URL with version interpolation */
    return "https://downloads.wordpress.org/release/wordpress-{$this->version}-no-content.zip";
  }
  
  /**
   * Gets full path to downloaded zip file
   * 
   * Constructs the full path where the WordPress zip file will be saved
   * in the temporary zip directory.
   * 
   * @return string Path to release.zip file
   */
  private function sourceZip(
  ): string {
    /* Join zip directory path with filename to get full path */
    return Util::join( 
      DIRECTORY_SEPARATOR, [
        $this->sourceDirectoryZip, "realese.zip" 
      ]
    );
  }

  /**
   * Downloads WordPress release from official repository
   * 
   * Opens a read stream to the WordPress download URL and writes
   * the contents to the local zip file. Outputs success message
   * with green colored path.
   * 
   * @return void
   */
  private function downloadSource(
  ): void {
    /* Download WordPress zip from official repository using file stream */
    file_put_contents(
      $this->sourceZip(), 
      fopen( $this->sourceUrl(), "r" )
    );

    /* Output success message with green colored path (ANSI escape codes) */
    fwrite( STDOUT, "  - Downloaded: \033[32m{$this->sourceZip()}\033[0m\n" );
  }

  /**
   * Extracts downloaded WordPress zip file
   * 
   * Uses ZipArchive to open the downloaded zip file and extract
   * all contents to the extraction directory. Outputs success message.
   * 
   * @return void
   */
  private function extractSource(
  ): void {
    /* Create ZipArchive instance and extract contents */
    $zipArchive = new ZipArchive();
    
    /* Open the downloaded zip file */
    $zipArchive->open( $this->sourceZip());
    
    /* Extract all contents to the extraction directory */
    $zipArchive->extractTo( $this->sourceDirectoryExtract);
    
    /* Close the zip archive to free resources */
    $zipArchive->close();

    /* Output success message with green colored path */
    fwrite( STDOUT, "  - Extracted: \033[32m{$this->sourceZip()}\033[0m\n" );
  }

  /**
   * Processes and copies a single file to target location
   * 
   * Extracts the relative path, calculates progress percentage,
   * displays progress message, and creates a File instance to handle
   * the actual copy operation.
   * 
   * @param SplFileInfo $splFileInfo File information object from iterator
   * @param int $index Current file index (1-based)
   * @param int $all Total number of files to process
   * @return File New File instance representing the copied file
   */
  private function moveFile(
    SplFileInfo $splFileInfo,
    int $index = 0,
    int $all = 0
  ): File {
    /* Extract relative path by removing the extract/wordpress prefix */
    [, $path ] = explode(
      Util::join(
      DIRECTORY_SEPARATOR, [ "extract", "wordpress" ]),
      $splFileInfo->getPath()
    );

    /* Calculate percentage progress with 2 decimal places */
    $perc = bcmul(
      bcdiv( $index, $all, 4 ), 
      100, 2
    );

    /* Output progress message with file count and percentage (overwrites previous line) */
    fwrite( 
      STDOUT,
      Util::sprintFormat(
        "\033[2K\r  - Installing %s de %s file %s: \033[32m%s\033[0m", [ 
          $index, $all, "{$perc}%", $splFileInfo->getFilename()
        ]
      )
    );

    /* Create File instance to handle copying from source to target with cleaned path */
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
   * Creates a recursive iterator to traverse all files in the extracted
   * WordPress directory, collects them in an array, then processes each
   * file with progress tracking.
   * 
   * @param array $splFileInfoArr Array to collect file information objects
   * @return void
   */
  private function moveToTarget(
    array $splFileInfoArr = []
  ): void {
    /* Create recursive iterator to traverse all files in WordPress directory */
    $splFileInfoIterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator(
        $this->sourceDirectoryExtractWordpress,
        RecursiveDirectoryIterator::SKIP_DOTS
      )
    );

    /* Collect all file objects (skip directories) into array for counting */
    foreach($splFileInfoIterator as $splFileInfo){
      if( $splFileInfo->isFile() ){
        $splFileInfoArr[] = $splFileInfo;
      } 
    }

    /* Iterate through collected files and move each one with progress tracking */
    foreach($splFileInfoArr as $index =>  $splFileInfo){
      $this->moveFile(
        $splFileInfo, 
        $index + 1, 
        Util::sizeArray( $splFileInfoArr )
      );
    } 

    /* Clear the progress line after completion */
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

    /** Write configuration to wp-config.php */
    file_put_contents(
      __DIR__ . "/../Core/wp-config.php", 
      $createConfig->joinWithBreak()
    );

    /** Output success message */
    fwrite( STDOUT, "\033[0m  - WordPress configuration file created (\033[32mwp-config.php\033[0m).\n" );
    fwrite( STDOUT, "\033[0m  - Installation completed successfully.\n" );
  }
}