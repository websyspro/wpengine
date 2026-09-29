<?php

namespace Websyspro\WpEngine\Shareds;

use PDO;
use PDOException;
use PDOStatement;
use Websyspro\Logger\Terminal;
use WP_SQLite_DB;

use function sizeof;
use function sprintf;

class WordpressImport
{
  private PDO $handleSource;
  private PDO $handleTarget;
  private int $offSet;
  private int $limit;
  private int $page;
  private int $totalRows;
  private array $rows;
  private array $fields;
  private array $variables = [];
  private array $values = [];

  public function __construct(
    private string $host = "localhost",
    private string $port = "3306",
    private string $name = "edocente_admin",
    private string $user = "root",
    private string $pass = "qazwsx",
    private string|null $base = null,
    private WP_SQLite_DB|null $wpdb = null
  ){
    if( $this->base ){
      $this->handleSource = new PDO( $this->handleSourceDNS(), $this->user, $this->pass );
      $this->handleTarget = new PDO( $this->handleTargetDNS() );
    }
  }

  private function handleSourceDNS(
  ): string {
    return "mysql:host={$this->host};port={$this->port};dbname={$this->name};charset=utf8mb4";
  }  

  private function handleTargetDNS(
  ): string {
    return "sqlite:{$this->base}src/database/.ht.sqlite";
  }

  public function importStructureFromTable(
  ): void {
    Terminal::init()
      ->green( "MySQL / MariaDB to Sqlite" )->eof()
      ->green( "Importing table structure" )->eof()->eof();

    foreach( $this->selectTablesFromSource() as $row ){
      if( $this->selectHasTableFromTarget( $row->tableName ) === false ){
        $statement = $this->handleSource->query(
          "Show Create Table {$row->tableName}"
        );

        if( $statement instanceof PDOStatement ){
          if( $this->wpdb instanceof WP_SQLite_DB ){
            if( $this->wpdb->query( $statement->fetchColumn( 1 ))){
              Terminal::init()
                ->spc()->text( "-" )->spc()
                ->green( $row->tableName )->spc()
                ->text( "successfully created." )->eof();
            }
          }
        }
      } else {
        Terminal::init()
          ->spc()->text( "-" )->spc()
          ->cyan( $row->tableName )->spc()
          ->text( "already exists." )->eof()->eof();
      }
    }
  }

  public function importRegisterFromTable(
  ): void {
    Terminal::init()
      ->green( "Importing Records" )->eof()->eof();

    foreach( $this->selectTablesFromSource() as $row ){
      if( in_array( $row->tableName, [ "wp_wfls_2fa_secrets", "wp_wfls_settings" ])){
        continue;
      }

      if( $this->selectHasTableFromTarget( $row->tableName )){
        if( $this->handleTarget->query( "Delete From '{$row->tableName}'" )){
          Terminal::init()
            ->spc()->text( "-" )->spc()
            ->text( "Clear table:" )->spc()
            ->green( $row->tableName )->eof();
        }

        $this->totalRows = 0;
        $this->limit = 512;
        $this->page = 0;

        do {
          $this->variables = [];
          $this->values = [];
          $this->rows = [];

          $this->selectOffSet();

          $this->selectRegister( $row->tableName );

          if( $this->selectedRows()){
            foreach( $this->rows as $regster ){
              $this->fields = array_map( 
                fn(string $field) => "'{$field}'", array_keys( $regster )
              );

              array_push( $this->variables, sprintf(
                "( %s )", implode( ", ", array_map( fn() => "?", $regster ) )
              ));

              foreach( $regster as $vals ){
                array_push( $this->values, $vals );
              }              
            }

            try {
              $statement = $this->handleTarget->prepare(
                sprintf( "Insert Into '{$row->tableName}' (%s) Values %s", 
                  implode( ", ", $this->fields ),
                  implode( ", ", $this->variables )
                )
              );

              $statement->execute( $this->values );
            } catch( PDOException $exception ){
              Terminal::init()
                ->text( "Error:" )->spc()
                ->cyan( $exception->getMessage() )->eof();
                exit();
            }
          }

          $this->page++;
          $this->totalRows += sizeof( $this->rows );
        } while ( $this->selectedRows() );

        Terminal::init()->spc()
          ->spc()->text( "-" )->spc()
          ->cyan( "populated table" )->spc()
          ->text( $row->tableName )->spc()
          ->cyan( "with" )->spc()
          ->text( $this->totalRows )->spc()
          ->cyan( "registers" )->eof();
      }
    }

    Terminal::init()->cyan( "Finished" )->eof();
  }  

  private function selectTablesFromSource(
  ): array {
    $statement = $this->handleSource->query( 
      "Select table_name as tableName 
         From information_schema.tables 
        Where table_schema = database()"
    );

    if( $statement instanceof PDOStatement ){
      return $statement->fetchAll( PDO::FETCH_OBJ );
    }

    return [];
  }

  private function selectHasTableFromTarget(
    string $tableName
  ): bool {
    $statement = $this->handleTarget->query(
      "Select Exists ( 
       Select 1 
         From sqlite_master 
        Where type = 'table' 
          And name = '{$tableName}' 
      )"
    );

    if( $statement instanceof PDOStatement ){
      return (int)$statement->fetchColumn() === 1;
    }

    return false;
  }

  private function selectOffSet(
  ): void {
    $this->offSet = $this->page * $this->limit;
  }

  private function selectRegisterFromUsers(
    string $tableName,
    string $tableNameKey
  ): string {
    return sprintf(
      "Select * 
         From {$tableName}
        Where {$tableName}.{$tableNameKey} in ( 
       Select wp_usermeta.user_ID
         From (
       Select wp_usermeta.user_ID
         From wp_usermeta
        Where wp_usermeta.meta_key = 'wp_capabilities'
          And wp_usermeta.meta_value in ( 'a:1:{s:6:\"editor\";b:1;}', 'a:1:{s:13:\"administrator\";b:1;}' )
        Union All
       Select wp_usermeta.user_ID
         From (
       Select wp_usermeta.user_ID
         From wp_usermeta
        Where wp_usermeta.meta_key = 'wp_capabilities'
          And wp_usermeta.meta_value in ( 'a:1:{s:13:\"standard_user\";b:1;}' )
        Limit 0, 24 )
           As wp_usermeta )
           As wp_usermeta )
        Limit {$this->offSet}, {$this->limit}"
    );
  }  

  private function selectRegisterDefault(
    string $tableName
  ): string {
    return "Select * From {$tableName} Limit {$this->offSet}, {$this->limit}";
  }

  private function selectRegister(
    string $tableName
  ): void {
    $statement = $this->handleSource->query( match( $tableName ){
      "wp_usermeta" => $this->selectRegisterFromUsers( $tableName, "user_ID" ),
      "wp_users" => $this->selectRegisterFromUsers( $tableName, "ID" ),
      default => $this->selectRegisterDefault( $tableName )
    });

    if( $statement instanceof PDOStatement ){
      $this->rows = $statement->fetchAll( PDO::FETCH_ASSOC );
    };
  }

  private function selectedRows(
  ): bool {
    return sizeof( $this->rows ) !== 0;
  }
}