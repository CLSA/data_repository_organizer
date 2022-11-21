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

function move_from_temporary_to_invalid( $file_or_dir )
{
  // make sure the parent directory exists
  $rename_from = $file_or_dir;
  $rename_to = preg_replace(
    sprintf( '#/%s/#', TEMPORARY_DIR ),
    sprintf( '/%s/', INVALID_DIR ),
    $file_or_dir
  );
  $destination_dir = preg_replace( '#/[^/]+$#', '', $rename_to );

  // make sure the destination directory exists
  if( !is_dir( $destination_dir ) ) mkdir( $destination_dir, 0755, true );

  // if the rename_to file or dir already exists then delete it
  if( file_exists( $rename_to ) ) exec( sprintf( 'rm -rf %s', $rename_to ) );

  // move the file
  rename( $rename_from, $rename_to );
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

/** 
 * Sends a curl request to the opal server(s)
 * 
 * @param array(key->value) $arguments The url arguments as key->value pairs (value may be null)
 * @return curl resource
 * @access private
 */
function opal_send( $arguments, $file_handle = NULL )
{
  $curl = curl_init();

  $code = 0;

  // prepare cURL request
  $headers = array(
    sprintf( 'Authorization: X-Opal-Auth %s',
             base64_encode( sprintf( '%s:%s', OPAL_USERNAME, OPAL_PASSWORD ) ) ),
    'Accept: application/json' );

  $url = sprintf( 'https://%s:%d/ws', OPAL_SERVER, OPAL_PORT );
  $postfix = array();
  foreach( $arguments as $key => $value )
  {   
    if( in_array( $key, array( 'counts', 'offset', 'limit', 'pos', 'select' ) ) )
      $postfix[] = sprintf( '%s=%s', $key, $value );
    else $url .= is_null( $value ) ? sprintf( '/%s', $key ) : sprintf( '/%s/%s', $key, rawurlencode( $value ) );
  }

  if( 0 < count( $postfix ) ) $url .= sprintf( '?%s', implode( '&', $postfix ) );

  // set URL and other appropriate options
  curl_setopt( $curl, CURLOPT_URL, $url );
  curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
  curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
  curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, OPAL_TIMEOUT );

  if( !is_null( $file_handle ) )
  {   
    //curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'PUT' );
    curl_setopt( $curl, CURLOPT_PUT, true );
    curl_setopt( $curl, CURLOPT_INFILE, $file_handle );
    curl_setopt( $curl, CURLOPT_INFILESIZE, fstat( $file_handle )['size'] );
    $headers[] = 'Content-Type: application/json';
  }

  curl_setopt( $curl, CURLOPT_HTTPHEADER, $headers );

  $response = curl_exec( $curl );
  $code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );

  if( array_key_exists( 'valueSet', $arguments ) && 404 == $code )
  {
    // ignore 404 and set response to null
    $response = NULL;
  }
  else if( 200 != $code )
  {
    throw new \Exception( sprintf(
      'Unable to connect to Opal service for url "%s" (code: %s)',
      $url,
      $code
    ) );
  }

  return $response;
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
