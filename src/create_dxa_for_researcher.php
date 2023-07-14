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
  $size = explode( ' ', @shell_exec( sprintf( 'identify -format "%%w %%h" %s', $dicom_filename ) ) );
  if( 2 != count( $size ) ) return 'Invalid file';
  $width = (int) $size[0];
  $height = (int) $size[1];

  $chop_x = 386;
  $chop_y = 148;

  if( 'forearm' == $type && 1200 == $width && 1320 == $height )
  {
    // use the default measurements
  }
  else if( 'hip' == $type && 1200 == $width && ( ( 1830 <= $height && $height <= 1964 ) || 1320 == $height ) )
  {
  }
  else if( 'wbody' == $type && 1440 == $width && 1585 <= $height && $height <= 1760 )
  {
  }
  else
  {
    return sprintf( 'Unexected type/geometry: %s (%d, %d)', $type, $width, $height );
  }

  $command = sprintf(
    'convert -chop %dx%d %s %s',
    $chop_x,
    $chop_y,
    format_filename( $dicom_filename ),
    format_filename( $image_filename )
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
$arguments->add_option( 't', 'type', 'The type of DXA scan being processed (eg: wbody, hip, forearm)', true, 'unknown' );
$arguments->add_input( 'INPUT', 'The filename of the DXA DICOM file to convert' );
$arguments->add_input( 'OUTPUT', 'The filename of the generated JPEG file' );

$args = $arguments->parse_arguments( $argv );

$type = $args['option_list']['type'];
if( 0 == strlen( $type ) ) $type = 'unknown';
$dicom_filename = $args['input_list']['INPUT'];
$image_filename = $args['input_list']['OUTPUT'];

$result = create_dxa_for_researcher( $type, $dicom_filename, $image_filename );
if( is_string( $result ) ) printf( "%s\n", $result );
