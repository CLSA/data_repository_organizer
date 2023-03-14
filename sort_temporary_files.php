<?php
require_once( 'settings.ini.php' );
require_once( 'src/common.php' );
require_once( 'src/arguments.class.php' );

////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// build the command argument details, then parse the passed args
$arguments = new arguments;
$arguments->set_description(
  "Analyses unsorted files in the temporary directory and files them appropriately."
);
$arguments->add_option( 'd', 'debug', 'Runs in test mode, no files will be affected.' );
$arguments->add_option( 'k', 'keep_files', 'Do not delete any files from the temporary directory.' );
$arguments->add_option( 'v', 'verbose', 'Shows more details when running the script.' );
$arguments->add_option( 's', 'study', 'The name of the study the data belongs to', true, 'clsa' );
$arguments->add_option( 'p', 'phase', 'The phase of the study the data belongs to', true, 1 );
$arguments->add_option( 'i', 'identifier', 'The name of the identifier used by filenames', true, false );
$arguments->add_input( 'DATA_TYPE', 'The type of data to process (actigraph, audio, ticwatch)' );

$args = $arguments->parse_arguments( $argv );

define( 'TEST_ONLY', array_key_exists( 'debug', $args['option_list'] ) );
define( 'KEEP_FILES', array_key_exists( 'keep_files', $args['option_list'] ) );
define( 'VERBOSE', array_key_exists( 'verbose', $args['option_list'] ) );
$identifier_name = $args['option_list']['identifier'];
$study = $args['option_list']['study'];
$phase = $args['option_list']['phase'];
$data_type = $args['input_list']['DATA_TYPE'];

if( !in_array( $data_type, ['actigraph', 'audio', 'ticwatch'] ) )
{
  fatal_error(
    sprintf( "Invalid DATA_TYPE \"%s\", aborting", $data_type ),
    10
  );
}

check_directories();

if( in_array( $data_type, ['actigraph', 'ticwatch'] ) )
{
  // make sure an identifier name was provided
  if( !$identifier_name ) fatal_error( "No identifier provided, aborting", 11 );

  if( 'actigraph' == $data_type ) process_actigraph_files( $identifier_name, $study, $phase );
  else if( 'ticwatch' == $data_type ) process_ticwatch_files( $identifier_name, $study, $phase );
}
else if( 'audio' == $data_type ) process_audio_files();

exit( 0 );
