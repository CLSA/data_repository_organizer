<?php
require_once( 'settings.ini.php' );
require_once( 'src/common.php' );
require_once( 'src/arguments.class.php' );

/**
 * Checks for invalid DICOM files
 * @param string $data_path The relative path to the data to prepare (study/phase/category)
 * @param string $file_glob The relative path to the data to prepare (study/phase/category)
 */
function check_dicom_files( $data_path, $file_glob )
{
  // make sure the data path exists
  $data_path = sprintf( '%s/%s', DATA_DIR, $data_path );
  if( !file_exists( $data_path ) )
  {
    fatal_error( sprintf( 'No files exist for data path "%s"', $data_path ), 10 );
  }

  $glob = sprintf( '%s/*/%s', $data_path, $file_glob );
  $glob_files = glob( $glob );
  if( false === $glob_files )
  {
    VERBOSE && output( sprintf( 'Error while globbing for %s', $glob ) );
  }
  else if( 0 == count( $glob_files ) )
  {
    VERBOSE && output( sprintf( 'No files found for glob %s', $glob ) );
  }
  else
  {
    // only include files with filesize > 0
    $files = [];
    foreach( $glob_files as $filename ) if( 0 < filesize( $filename ) )
    {
      $result_code = 0;
      $output = NULL;
      exec(
        sprintf( "dcmdump -q %s", $filename ),
        $output,
        $result_code
      );
      if( 0 != $result_code ) printf( "%s is invalid (code %d)\n", $filename, $result_code );
      else if( VERBOSE ) printf( "%s is valid\n", $filename );
    }
  }
}


////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// build the command argument details, then parse the passed args
$arguments = new arguments;
$arguments->set_description(
  "Scans for invalid DICOM files.\n".
  "This script will check all DICOM files of non-zero length to make sure they are valid."
);
$arguments->add_option( 'v', 'verbose', 'Shows more details when running the script' );
$arguments->add_option( 'g', 'glob', 'Restrict to files matching a particular glob (eg: *.dcm)', true, '*' );
$arguments->add_input( 'DATA_PATH', 'The relative path to the data to prepare (eg: raw/clsa/1/dxa)' );

$args = $arguments->parse_arguments( $argv );

define( 'VERBOSE', array_key_exists( 'verbose', $args['option_list'] ) );
$file_glob = $args['option_list']['glob'];
$data_path = $args['input_list']['DATA_PATH'];

check_dicom_files( $data_path, $file_glob );
