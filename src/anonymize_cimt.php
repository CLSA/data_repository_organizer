<?php
set_time_limit( 60 ); // one minute
error_reporting( E_ALL | E_STRICT );
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
 * Anonymizes an ultrasound CIMT DICOM file by removing identifying tags
 * @param string $filename The name of the file to anonymize
 */
function anonymize( $filename )
{
  $dicom_tag = array(
    '0008,1010' => 'VIVID_I', // Station Name
    '0008,1070' => '',        // Operators Name
    '0010,0010' => '',        // Patient Name
    '0010,0020' => '',        // Patient ID
    '0010,1000' => '',        // Other Patient IDs
    '0008,0080' => 'CLSA',    // Instituion Name
    '0018,1000' => '',        // Device Serial Number
    '0008,1040' => 'NCC'      // Instituion Department Name
  );

  $modify_list = [];
  foreach( $dicom_tag as $key => $value )
  {
    $modify_list[] = sprintf( '-m "(%s)%s"', $key, is_null( $value ) ? '' : sprintf( '=%s', $value ) );
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
  "Removes all identifying DICOM tags from an ultrasound CIMT DICOM image.\n".
  "WARNING: the target file will be overwritten so make sure a backup exists before running this utility."
);
$arguments->add_option( 'd', 'debug', 'Outputs the script\'s commands without executing them' );
$arguments->add_input( 'FILENAME', 'The filename of the DICOM image' );

$args = $arguments->parse_arguments( $argv );

define( 'DEBUG', array_key_exists( 'debug', $args['option_list'] ) );
$filename = $args['input_list']['FILENAME'];

out( sprintf( 'Anonymizing file "%s"', $filename ) );
anonymize( $filename );
