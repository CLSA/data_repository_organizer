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

////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// build the command argument details, then parse the passed args
$arguments = new arguments;
$arguments->set_version( VERSION );
$arguments->set_description(
  "Removes all identifying information from DICOM image and ECG XML files.\n".
  "WARNING: the target file will be overwritten so make sure a backup exists before running this utility."
);
$arguments->add_option( 'd', 'debug', 'Outputs the script\'s commands without executing them' );
$arguments->add_option( 't', 'data_type', 'The type of file being anonymized', true );
$arguments->add_input( 'FILENAME', 'The name of the file to anonymize' );

$args = $arguments->parse_arguments( $argv );

define( 'DEBUG', array_key_exists( 'debug', $args['option_list'] ) );

if( !array_key_exists( 'data_type', $args['option_list'] ) )
{
  fatal_error( 'Cannot proceed without specifying the data type', 10 );
}

$data_type = $args['option_list']['data_type'];
$filename = $args['input_list']['FILENAME'];

if( !in_array( $data_type, ['cimt', 'dxa', 'ecg'] ) )
{
  fatal_error(
    sprintf( "Invalid DATA_TYPE \"%s\", aborting", $data_type ),
    11
  );
}

out( sprintf( 'Anonymizing file "%s"', $filename ) );
if( 'ecg' == $data_type ) anonymize_ecg( $filename, DEBUG );
else anonymize_dicom( $data_type, $filename, DEBUG );
