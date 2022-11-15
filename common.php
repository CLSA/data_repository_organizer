<?php
ini_set( 'display_errors', '0' );
error_reporting( E_ALL | E_STRICT );

// function for writing to the log
function output( $message )
{
  printf( "%s> %s\n", date( 'Y-m-d (D) H:i:s' ), $message );
}

function fatal_error( $message, $code )
{
  output( sprintf( 'ERROR: %s', $message ) );
  exit( $code );
}

function get_cenozo_db()
{
  return new \mysqli( CENOZO_DB_HOSTNAME, CENOZO_DB_USERNAME, CENOZO_DB_PASSWORD, CENOZO_DB_DATABASE );
}

function move_dir( $source, $destination )
{
  printf( "\n%s\n%s\n\n", $source, $destination );

  // make sure the destination directory exists
  mkdir( $destination, 0755, true );

  // move the directory
  rename( $source, $destination );
}

function move_dir_from_temporary_to_invalid( $dir )
{
  move_dir(
    $dir,
    preg_replace(
      sprintf( '#/%s/#', TEMPORARY_DIR ),
      sprintf( '/%s/', INVALID_DIR ),
      $dir
    )
  );
}

// find and remove all empty directories
function remove_dir( $dir )
{
  // first remove all empty sub directories
  foreach( glob( sprintf( '%s/*', $dir ), GLOB_ONLYDIR ) as $subdir ) remove_dir( $subdir );

  // now see if the directory is empty and remove it is it is
  if( 0 == count( glob( sprintf( '%s/*', $dir ) ) ) ) rmdir( $dir );
}

// Reads the id_lookup file and returns an array containing Study ID => UID pairs
function get_study_uid_lookup( $identifier_name )
{
  $cenozo_db = get_cenozo_db();
  $result = $cenozo_db->query( sprintf(
    'SELECT participant.uid, participant_identifier.value '.
    'FROM participant '.
    'JOIN identifier '.
    'JOIN participant_identifier '.
      'ON identifier.id = participant_identifier.identifier_id '.
      'AND participant.id = participant_identifier.participant_id '.
    'WHERE identifier.name = "%s"',
    $cenozo_db->real_escape_string( $identifier_name )
  ) );
  $cenozo_db->close();

  if( false === $result ) throw new Exception( 'Unable to get study UID lookup data.' );

  $data = [];
  while( $row = $result->fetch_assoc() ) $data[$row['value']] = $row['uid'];
  $result->free();

  return $data;
}


// move to the scripts root directory
chdir( dirname( __FILE__ ) );

require_once 'settings.ini.php';

// Make sure the destination directories exist
$test_dir_list = array(
  DATA_DIR,
  sprintf( '%s/%s', DATA_DIR, TEMPORARY_DIR ),
  sprintf( '%s/%s', DATA_DIR, CLEANED_DIR ),
  sprintf( '%s/%s', DATA_DIR, INVALID_DIR )
);
foreach( $test_dir_list as $dir )
{
  if( !is_dir( $dir ) ) fatal_error( sprintf( 'Expected directory, "%s", not found', $dir ), 1 );
  if( !TEST_ONLY && !is_writable( $dir ) ) fatal_error( sprintf( 'Cannot write to directory "%s"', $dir ), 2 );
}
