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
  private int $page;
  private array $rows;
  private array $fields;
  private array $variables;
  private array $values;

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
    foreach( $this->selectTablesFromSource() as $row ){
      if( $this->selectHasTableFromTarget( $row->tableName ) === false ){
        $statement = $this->handleSource->query(
          "Show Create Table {$row->tableName}"
        );

        if( $statement instanceof PDOStatement ){
          if( $this->wpdb instanceof WP_SQLite_DB ){
            if( $this->wpdb->query( $statement->fetchColumn( 1 ))){
              Terminal::init()
                ->text( "Creating table:" )->spc()
                ->green( $row->tableName )->eof();
            }
          }
        }
      } else {
        Terminal::init()
          ->text( "Existed table:" )->spc()
          ->cyan( $row->tableName )->eof();
      }
    }
  }

  public function importRegisterFromTable(
  ): void {
    foreach( $this->selectTablesFromSource() as $row ){
      if( in_array( $row->table_name, [ "wp_wfls_2fa_secrets", "wp_wfls_settings" ])){
        continue;
      }

      if( $this->selectHasTableFromTarget( $row->table_name )){
        if( $this->handleTarget->query( "Delete From '{$row->tableName}'" )){
          Terminal::init()
            ->text( "Clear table:" )->spc()
            ->green( $row->tableName )->eof();
        }

        $this->offSet = 512;
        $this->page = 0;

        do {
          $this->rows = $this->selectRegister( $row->tableName );
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
                sprintf( "Insert Into '%s' (%s) Values %s", 
                  implode( ", ", $this->fields ),
                  implode( ", ", $this->variables )
                )
              );

              $statement->execute( $this->values );

            } catch( PDOException $exception ){
              Terminal::init()
                ->text( "Error:" )->spc()
                ->cyan( $exception->getMessage() )->eof()
                ->cyan( $statement->queryString )->eof();
                exit();
            }
          }


          $this->page++;
        } while ( $this->selectedRows() );
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

  private function selectRegisterFromUsers(
    string $tableName,
    string $tableNameKey
  ): PDOStatement {
    return $this->handleSource->prepare(
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
        Limit 0, 512 )
           As wp_usermeta )
           As wp_usermeta )
        Limit ?, ?"
    );
  }  

  private function selectRegisterDefault(
    string $tableName
  ): PDOStatement {
    return $this->handleSource->prepare(
      "Select * From {$tableName} Limit ?, ?"
    );
  }

  private function selectRegister(
    string $tableName
  ): array {
    $statement = match( $tableName ){
      "wp_users" => $this->selectRegisterFromUsers( $tableName, "ID" ),
      "wp_usermeta" => $this->selectRegisterFromUsers( $tableName, "user_ID" ),
        default => $this->selectRegisterDefault( $tableName )
    };

    if( $statement instanceof PDOStatement ){
      return $statement->fetchAll( PDO::FETCH_ASSOC );
    }

    return [];
  }

  private function selectedRows(
  ): bool {
    return sizeof( $this->rows ) !== 0;
  }
}