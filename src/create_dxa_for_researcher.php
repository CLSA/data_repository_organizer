<?php
require_once( 'common.php' );
require_once( 'arguments.class.php' );

/**
 * Generates a redacted JPEG representation of a DXA DICOM image
 * @param string $type The type of DXA scan
 * @param string $dicom_filename The input DXA DICOM image filename
 * @param string $image_filename The output JPEG image filename
 */
function create_dxa_for_researcher( $type, $dicom_filename, $image_filename )
{
  $crop = NULL;

  if( 'forearm' == $type )
  {
    $crop = '820x855+380+155';
  }
  else if( 'hip' == $type )
  {
    $crop = '810x1530+390+153';
  }
  else if( 'wbody_bca' == $type )
  {
    $crop = '607x872+489+136';
  }
  else if( 'wbody_bmd' == $type )
  {
    $crop = '334x757+492+176';
  }
  else
  {
    return sprintf( 'Unknown type: %s', $type );
  }

  $command = sprintf(
    'dcmj2pnm --write-jpeg %s %s && convert %s -crop %s +repage %s',
    $dicom_filename,
    $image_filename,
    $image_filename,
    $crop,
    $image_filename
  );

  shell_exec( $command );
  return true;
}


////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// build the command argument details, then parse the passed args
$arguments = new arguments;
$arguments->set_description(
  "Generates a cropped JPEG representation of a DXA DICOM image\n".
  "This script will convert a DXA DICOM file to a JPEG file specifically for release to researchers."
);
$arguments->add_option(
  't',
  'type',
  'The type of DXA scan being processed (eg: forearm, hip, wbody_bca, or wbody_bmd)',
  true,
  'unknown'
);
$arguments->add_input( 'INPUT', 'The filename of the DXA DICOM file to convert' );
$arguments->add_input( 'OUTPUT', 'The filename of the generated JPEG file' );

$args = $arguments->parse_arguments( $argv );

$type = $args['option_list']['type'];
if( 0 == strlen( $type ) ) $type = 'unknown';
$dicom_filename = $args['input_list']['INPUT'];
$image_filename = $args['input_list']['OUTPUT'];

$result = create_dxa_for_researcher( $type, $dicom_filename, $image_filename );
if( is_string( $result ) ) printf( "%s\n", $result );
