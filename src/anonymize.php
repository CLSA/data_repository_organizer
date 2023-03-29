<?php
set_time_limit( 60 ); // one minute
error_reporting( E_ALL | E_STRICT );
require_once( 'common.php' );
require_once( 'arguments.class.php' );

define( 'VERSION', '1.0' );

/**
 * Prints datetime-indexed messages to stdout
 * @param string $message
 */
function out( $message )
{
  if( !DEBUG ) printf( "%s\n", $message );
}

/**
 * Anonymizes a DICOM file by removing identifying tags
 * @param string $data_type The type of data being anonymize (cimt or dxa)
 * @param string $filename The name of the file to anonymize
 */
function anonymize( $data_type, $filename )
{
  $tag_list = [
    '0008,1010' => '',      // Station Name
    '0008,0080' => 'CLSA',  // Instituion Name
    '0008,1040' => 'NCC',   // Instituion Department Name
    '0008,1070' => '',      // Operators Name
    '0010,0010' => '',      // Patient Name
    '0010,0020' => '',      // Patient ID
    '0010,1000' => '',      // Other Patient IDs
    '0018,1000' => '',      // Device Serial Number
  ];
  
  if( 'cimt' == $data_type )
  {
    $tag_list['0008,1010'] = 'VIVID_I'; // Station Name
  }
  else if( 'dxa' == $data_type )
  {
    // Unknown Tags & Data
    $tag_list['0019,1000'] = NULL;
    $tag_list['0023,1000'] = NULL;
    $tag_list['0023,1001'] = NULL;
    $tag_list['0023,1002'] = NULL;
    $tag_list['0023,1003'] = NULL;
    $tag_list['0023,1004'] = NULL;
    $tag_list['0023,1005'] = NULL;
  }
  else return;

  $modify_list = [];
  foreach( $tag_list as $tag => $value )
  {
    $modify_list[] = sprintf( '-m "(%s)%s"', $tag, is_null( $value ) ? '' : sprintf( '=%s', $value ) );
  }

  $command = sprintf( 'dcmodify -nb -nrc -imt %s %s', implode( ' ', $modify_list ), $filename );

  $result_code = 0;
  $output = NULL;
  DEBUG ? printf( "%s\n", $command ) : exec( $command, $output, $result_code );

  if( 0 < $result_code ) printf( implode( "\n", $output ) );
}


////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// build the command argument details, then parse the passed args
$arguments = new arguments;
$arguments->set_version( VERSION );
$arguments->set_description(
  "Removes all identifying DICOM tags from a DICOM image.\n".
  "WARNING: the target file will be overwritten so make sure a backup exists before running this utility."
);
$arguments->add_option( 'd', 'debug', 'Outputs the script\'s commands without executing them' );
$arguments->add_option( 't', 'data_type', 'The type of file being anonymized', true );
$arguments->add_input( 'FILENAME', 'The filename of the DICOM image' );

$args = $arguments->parse_arguments( $argv );

define( 'DEBUG', array_key_exists( 'debug', $args['option_list'] ) );

if( !array_key_exists( 'data_type', $args['option_list'] ) )
{
  fatal_error( 'Cannot proceed without specifying the data type', 10 );
}

$data_type = $args['option_list']['data_type'];
$filename = $args['input_list']['FILENAME'];

if( !in_array( $data_type, ['cimt', 'dxa'] ) )
{
  fatal_error(
    sprintf( "Invalid DATA_TYPE \"%s\", aborting", $data_type ),
    11
  );
}

out( sprintf( 'Anonymizing file "%s"', $filename ) );
anonymize( $data_type, $filename );
