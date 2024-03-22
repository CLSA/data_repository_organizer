<?php
ini_set( 'display_errors', '0' );
error_reporting( E_ALL | E_STRICT );
ini_set( 'date.timezone', 'US/Eastern' );

function out( $message ) { if( !defined( 'DEBUG' ) || !DEBUG ) printf( "%s\n", $message ); }
function output( $message ) { printf( "%s> %s\n", date( 'Y-m-d (D) H:i:s' ), $message ); }
function fatal_error( $message, $code ) { output( sprintf( 'ERROR: %s', $message ) ); exit( $code ); }

/**
 * Utilities calss used throughout the software
 */
class util
{
  public static function get_cenozo_db()
  {
    mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
    return new \mysqli( CENOZO_DB_HOSTNAME, CENOZO_DB_USERNAME, CENOZO_DB_PASSWORD, CENOZO_DB_DATABASE );
  }


  public static function check_directories()
  {
    // Make sure the destination directories exist
    $test_dir_list = array(
      DATA_DIR,
      sprintf( '%s/%s', DATA_DIR, TEMPORARY_DIR ),
      sprintf( '%s/%s', DATA_DIR, INVALID_DIR )
    );
    foreach( $test_dir_list as $dir )
    {
      if( !is_dir( $dir ) ) fatal_error( sprintf( 'Expected directory, "%s", not found', $dir ), 33 );
      if( !TEST_ONLY && !is_writable( $dir ) ) fatal_error( sprintf( 'Cannot write to directory "%s"', $dir ), 34 );
    }
  }


  public static function format_filename( $filename )
  {
    return sprintf( '"%s"', str_replace( '"', '\"', $filename ) );
  }


  /**
   * Sends a curl request to the opal server(s)
   * 
   * @param array(key->value) $arguments The url arguments as key->value pairs (value may be null)
   * @param boolean $alternate Whether to use the alternate Opal server (updated daily instead of weekly)
   * @return curl resource
   */
  public static function opal_send( $arguments, $alternate = false )
  {
    $curl = curl_init();

    $code = 0;

    $user = $alternate ? OPALALT_USERNAME : OPAL_USERNAME;
    $pass = $alternate ? OPALALT_PASSWORD : OPAL_PASSWORD;
    $url = $alternate ? OPALALT_URL : OPAL_URL;
    $timeout = $alternate ? OPALALT_TIMEOUT : OPAL_TIMEOUT;

    // prepare cURL request
    $headers = array(
      sprintf( 'Authorization: X-Opal-Auth %s', base64_encode( sprintf( '%s:%s', $user, $pass ) ) ),
      'Accept: application/json'
    );

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
    curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, $timeout );
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


  /**
   * Compresses a file or directory and return the full path to the compressed file
   */
  public static function compress_file( $filename )
  {
    $compressed_filename = NULL;

    // determine if the input is already a gzipped file
    $output = NULL;
    $result_code = NULL;
    exec( sprintf( 'file %s', $filename ), $output, $result_code );
    if( 0 == $result_code && 0 < count( $output ) && preg_match( '#gzip compressed data#', $output[0] ) )
    {
      printf( "A\n" );
      $compressed_filename = $filename;
    }
    else
    {
      $output = NULL;
      $result_code = NULL;
      printf( "B: gzip %s\n", $filename );
      exec( sprintf( 'gzip -nf %s', $filename ), $output, $result_code );
      $compressed_filename = 0 == $result_code ? sprintf( '%s.gz', $filename ) : NULL;
    }

    printf( "C: %s\n", $compressed_filename );
    return $compressed_filename;
  }


  /**
   * Decompresses a gzipped file and returns the full path to the decompressed file
   */
  public static function decompress_file( $gzip_filename )
  {
    $decompressed_filename = NULL;

    // determine if the input is a gzipped file
    $output = NULL;
    $result_code = NULL;
    exec( sprintf( 'file %s', $gzip_filename ), $output, $result_code );
    if( 0 == $result_code && 0 < count( $output ) && preg_match( '#gzip compressed data#', $output[0] ) )
    {
      // create a temporary file to copy the original gzipped to
      $working_gzfilename = sprintf(
        '/tmp/%s_%s',
        bin2hex( openssl_random_pseudo_bytes( 8 ) ),
        basename( $gzip_filename )
      );

      // add the .gz extension if there isn't one already
      if( !preg_match( '#\.gz$#', $working_gzfilename ) ) $working_gzfilename .= '.gz';

      $output = NULL;
      copy( $gzip_filename, $working_gzfilename );
      exec( sprintf( 'gunzip -f %s', $working_gzfilename ), $output, $result_code );

      // determine what the decompressed file will be
      if( 0 == $result_code ) $decompressed_filename = preg_replace( '#\.gz$#', '', $working_gzfilename );
    }

    return $decompressed_filename;
  }
}
