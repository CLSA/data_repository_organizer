<?php
require_once( 'settings.ini.php' );
require_once( 'src/common.php' );
require_once( 'src/arguments.class.php' );

////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// build the command argument details, then parse the passed args
$arguments = new arguments;
$arguments->set_description(
  "Prepares a standard data release.\n".
  "This script will create a copy of standardized participant data and apply a custom identifier.  ".
  "Note that only data belonging to participants provided in the identifier CSV file will be included.  ".
  "The CSV file should have a header row and contain two columns: the CLSA ID and identifier."
);
$arguments->add_option( 'v', 'verbose', 'Shows more details when running the script' );
$arguments->add_option( 'k', 'keep_files', 'Keep files which have already been copied.' );
$arguments->add_option(
  'c',
  'data_category',
  'Must be one of the following:'.
    "\n       dxa_forearm_bl, dxa_forearm_f1, dxa_forearm_f2, dxa_forearm_f3 (jpeg images, right and left)".
    "\n       dxa_hip_bl, dxa_hip_f1 (reanalysed jpeg images, right and left)".
    "\n       dxa_lateral_bl, dxa_lateral_f1, dxa_lateral_f2, dxa_lateral_f3 (original dcm file)".
    "\n       dxa_wbody_bl (reanalysed jpeg images, BCA and BMD)".
    "\n       retinal_bl, retinal_f1, retinal_f2, retinal_f3 (original jpeg images, right and left)",
  true
);
$arguments->add_input( 'RELEASE_NAME', 'The name of the release where files will be copied to' );
$arguments->add_input( 'IDENTIFIER_FILENAME', 'The CSV file containing the CLSA ID and identifier' );

$args = $arguments->parse_arguments( $argv );

define( 'VERBOSE', array_key_exists( 'verbose', $args['option_list'] ) );
$data_category = $args['option_list']['data_category'];
$keep_files = array_key_exists( 'keep_files', $args['option_list'] );
$release_name = $args['input_list']['RELEASE_NAME'];
$identifier_filename = $args['input_list']['IDENTIFIER_FILENAME'];

$type = NULL;
$phase = NULL;

// Add any new data categories here
if( 'dxa_forearm_bl' == $data_category ) { $type = 'forearm'; $phase = 1; }
else if( 'dxa_forearm_f1' == $data_category ) { $type = 'forearm'; $phase = 2; }
else if( 'dxa_forearm_f2' == $data_category ) { $type = 'forearm'; $phase = 3; }
else if( 'dxa_forearm_f3' == $data_category ) { $type = 'forearm'; $phase = 4; }
else if( 'dxa_hip_bl' == $data_category ) { $type = 'hip'; $phase = 1; }
else if( 'dxa_hip_f1' == $data_category ) { $type = 'hip'; $phase = 2; }
else if( 'dxa_lateral_bl' == $data_category ) { $type = ''; $phase = 1; }
else if( 'dxa_lateral_f1' == $data_category ) { $type = 'lateral'; $phase = 2; }
else if( 'dxa_lateral_f2' == $data_category ) { $type = 'lateral'; $phase = 3; }
else if( 'dxa_lateral_f3' == $data_category ) { $type = 'lateral'; $phase = 4; }
else if( 'dxa_wbody_bl' == $data_category ) { $type = 'wbody'; $phase = 1; }
else if( 'retinal_bl' == $data_category ) { $type = 'retinal'; $phase = 1; }
else if( 'retinal_f1' == $data_category ) { $type = 'retinal'; $phase = 2; }
else if( 'retinal_f2' == $data_category ) { $type = 'retinal'; $phase = 3; }
else if( 'retinal_f3' == $data_category ) { $type = 'retinal'; $phase = 4; }
else
{
  printf( "ERROR: Invalid data category \"%s\", aborting\n\n", $data_category );
  die();
}

// Implement any new data categories here
if( 'forearm' == $type )
{
  $data_path = sprintf( 'supplementary/clsa/%d/dxa', $phase );
  passthru( sprintf(
    'php %s/prepare_data.php %s %s %s -g "dxa_forearm_left.jpeg" %s',
    __DIR__,
    $release_name,
    $data_path,
    $identifier_filename,
    VERBOSE ? '-v' : ''
  ) );
  passthru( sprintf(
    'php %s/prepare_data.php %s %s %s -g "dxa_forearm_right.jpeg" %s',
    __DIR__,
    $release_name,
    $data_path,
    $identifier_filename,
    VERBOSE ? '-v' : ''
  ) );
}
else if( 'hip' == $type )
{
  $data_path = sprintf( 'supplementary/clsa/%d/dxa', $phase );
  passthru( sprintf(
    'php %s/prepare_data.php %s %s %s -g "dxa_hip_left.reanalysed.jpeg" %s',
    __DIR__,
    $release_name,
    $data_path,
    $identifier_filename,
    VERBOSE ? '-v' : ''
  ) );
  passthru( sprintf(
    'php %s/prepare_data.php %s %s %s -g "dxa_hip_right.reanalysed.jpeg" %s',
    __DIR__,
    $release_name,
    $data_path,
    $identifier_filename,
    VERBOSE ? '-v' : ''
  ) );
}
else if( 'lateral' == $type )
{
  $data_path = sprintf( 'raw/clsa/%d/dxa', $phase );
  passthru( sprintf(
    'php %s/prepare_data.php %s %s %s -g "dxa_lateral.dcm" %s',
    __DIR__,
    $release_name,
    $data_path,
    $identifier_filename,
    VERBOSE ? '-v' : ''
  ) );
}
else if( 'wbody' == $type )
{
  $data_path = sprintf( 'supplementary/clsa/%d/dxa', $phase );
  passthru( sprintf(
    'php %s/prepare_data.php %s %s %s -g "dxa_wbody_bca.reanalysed.jpeg" %s',
    __DIR__,
    $release_name,
    $data_path,
    $identifier_filename,
    VERBOSE ? '-v' : ''
  ) );
  passthru( sprintf(
    'php %s/prepare_data.php %s %s %s -g "dxa_wbody_bmd.reanalysed.jpeg" %s',
    __DIR__,
    $release_name,
    $data_path,
    $identifier_filename,
    VERBOSE ? '-v' : ''
  ) );
}
else if( 'retinal' == $type )
{
  $data_path = sprintf( 'raw/clsa/%d/retinal', $phase );
  passthru( sprintf(
    'php %s/prepare_data.php %s %s %s -g "retinal_[lru]*.jpeg" %s',
    __DIR__,
    $release_name,
    $data_path,
    $identifier_filename,
    VERBOSE ? '-v' : ''
  ) );
}
