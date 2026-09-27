<?php

defined( "BASE_DIR" ) || define(
  "BASE_DIR", realpath(
    dirname( __FILE__ ) 
  ) . DIRECTORY_SEPARATOR
);

$_SERVER['HTTP_HOST'] = "localhost";

// define putenv
putenv( "DATABASE_TYPE=sqlite" );
putenv( "DB_ENGINE=sqlite" );
putenv( "DB_DRIVER=mysql" );
putenv( "DB_NAME=.ht.sqlite3" );
putenv( "DB_USER=root" );
putenv( "DB_PASSWORD=qazwsx" );
putenv( "DB_HOST=localhost" );
putenv( "DB_CHARSET=utf8mb4" );
putenv( "DB_COLLATE=" );

ini_set( 'memory_limit', '5G' );

// Silence is golden.
require_once BASE_DIR . "vendor/autoload.php";

// Bootstrap WordPress (inicializa $wpdb com o translator MySQL → SQLite)
require_once BASE_DIR . "vendor/websyspro/wpengine/src/Core/wp-load.php";

use Websyspro\Logger\Terminal;
global $wpdb;

$handle_source = new PDO( "mysql:host=localhost;port=3306;dbname=edocente_admin;charset=utf8mb4", "root", "qazwsx" );
$handle_target = new PDO( "sqlite:src/database/.ht.sqlite" );
$handle_createds = false;

$handle_source_information_schema = $handle_source->query( "Select table_name as table_name From information_schema.tables Where table_schema = database()" );

foreach( $handle_source_information_schema->fetchAll( PDO::FETCH_OBJ ) as $information_schema ){
  if( in_array( $information_schema->table_name, [ "wp_wfls_2fa_secrets", "wp_wfls_settings" ])){
    continue;
  }

  if( $handle_createds ){
    $handle_target_statement = $handle_target->prepare( "Select Exists ( Select 1 From sqlite_master Where type = 'table' And name = ? )" );
    $handle_target_statement->execute([ $information_schema->table_name ]); 

    if( (bool)$handle_target_statement->fetchColumn() === false ){
      $handle_source_statement = $handle_source->query( "Show Create Table {$information_schema->table_name}" );

      if( $handle_source_statement ){
        $handle_source_statement_show_create_table = $handle_source_statement->fetchColumn(1);
        $wpdb->query( $handle_source_statement_show_create_table );

        Terminal::init()->text( "Created table" )->spc()->green( $information_schema->table_name )->line();
      }
    }
  } else {
    Terminal::init()->text( "Processando table" )->spc()->green( $information_schema->table_name )->line();
    $handle_target->query( "Delete From '{$information_schema->table_name}'" );

    $handle_source_offset = 512;
    $handle_source_page = 0;
    $handle_source_rows = 0;
    
    do {
      $handle_source_rows = [];

      if( in_array( $information_schema->table_name, [ "wp_users", "wp_usermeta" ])){
        if( $information_schema->table_name === "wp_users" ){
          $sprintf_query = (
            "select * 
               from {$information_schema->table_name}
              where {$information_schema->table_name}.ID in ( 
             select wp_usermeta.user_ID
               from (
             select wp_usermeta.user_ID
               from wp_usermeta
              where wp_usermeta.meta_key = 'wp_capabilities'
                and wp_usermeta.meta_value in ( 'a:1:{s:6:\"editor\";b:1;}', 'a:1:{s:13:\"administrator\";b:1;}' )
              union all
             select wp_usermeta.user_ID
               from (
             select wp_usermeta.user_ID
               from wp_usermeta
              where wp_usermeta.meta_key = 'wp_capabilities'
                and wp_usermeta.meta_value in ( 'a:1:{s:13:\"standard_user\";b:1;}' )
              limit 0, 512 )
                 as wp_usermeta )
                 as wp_usermeta )
              Limit %d, %d"
          );
        } else
        if( $information_schema->table_name === "wp_usermeta" ){
          $sprintf_query = (
            "select * 
               from {$information_schema->table_name}
              where {$information_schema->table_name}.user_ID in ( 
             select wp_usermeta.user_ID
               from (
             select wp_usermeta.user_ID
               from wp_usermeta
              where wp_usermeta.meta_key = 'wp_capabilities'
                and wp_usermeta.meta_value in ( 'a:1:{s:6:\"editor\";b:1;}', 'a:1:{s:13:\"administrator\";b:1;}' )
              union all
             select wp_usermeta.user_ID
               from (
             select wp_usermeta.user_ID
               from wp_usermeta
              where wp_usermeta.meta_key = 'wp_capabilities'
                and wp_usermeta.meta_value in ( 'a:1:{s:13:\"standard_user\";b:1;}' )
              limit 0, 512 )
                 as wp_usermeta )
                 as wp_usermeta )
              Limit %d, %d"
          );
        }

        $handle_source_statement = $handle_source->query( sprintf( $sprintf_query, $handle_source_page * $handle_source_offset, $handle_source_offset ));
        $handle_source_rows = $handle_source_statement->fetchAll( PDO::FETCH_ASSOC );
      } else {
        $handle_source_statement = $handle_source->query( sprintf( "Select * From {$information_schema->table_name} Limit %d, %d", $handle_source_page * $handle_source_offset, $handle_source_offset ));
        $handle_source_rows = $handle_source_statement->fetchAll( PDO::FETCH_ASSOC );
      }

      $handle_source_fild = [];
      $handle_source_vars = [];
      $handle_source_vals = [];
      
      if( sizeof( $handle_source_rows ) !== 0 ){
        foreach( $handle_source_rows as $handle_source_rows_vals ){
          $handle_source_fild = implode( ", ", array_map( fn(string $fild) => "'{$fild}'", array_keys( $handle_source_rows_vals )));
          array_push( $handle_source_vars, sprintf( "( %s )", implode( ", ", array_map( fn() => "?", $handle_source_rows_vals ) )));

          foreach( $handle_source_rows_vals as $name => $vals ){
            array_push( $handle_source_vals, $vals );
          }
        }

        try {
          $handle_target_insert = $handle_target->prepare( sprintf( "Insert Into '{$information_schema->table_name}' ({$handle_source_fild}) Values %s", implode( ", ", $handle_source_vars )));
          $handle_target_insert->execute( $handle_source_vals );
        } catch( PDOException $error ){
          Terminal::init()
            ->text( "Error:" )->spc()
            ->cyan( $error->getMessage() )->line()
            ->cyan( $handle_target_insert->queryString )->line();
          exit();
        }
      }

      $handle_source_page++;
    } while ( sizeof($handle_source_rows) !== 0 );
  }
  }

// Message Log end script
Terminal::init()->line( "End" );
