<?php
require_once( 'common.php' );
require_once( 'arguments.class.php' );

/**
 * Generates a redacted JPEG representation of a DXA DICOM image
 * @param string $type The type of DXA scan
 * @param string $dicom_filename The input DXA DICOM image filename
 * @param string $image_filename The output JPEG image filename
 */
function create_dxa_for_participant( $type, $dicom_filename, $image_filename )
{
  $size = explode( ' ', @shell_exec( sprintf( 'identify -format "%%w %%h" %s', $dicom_filename ) ) );
  if( 2 != count( $size ) ) return 'Invalid file';
  $width = (int) $size[0];
  $height = (int) $size[1];

  $redact = [
    'x' => 169, // the x-coordinate of the left side of all information boxes
    'name_y' => $height - 182,
    'dob_y' => $height - 447, // varies for all image types
    'box_width' => 78, // the width of all patient information boxes
    'box_height' => 25, // the height of all patient information boxes
    'box_margin' => 8, // the vertical margin between all information boxes
  ];

  $label = [
    'x' => 30, // the x-coordinate of the left side of the label
    'y_offset' => 160, // the distance from the bottom of the top of the label
    'width' => 1140, // the width of the label
    'height' => 115, // the height of the label
  ];

  if( 'forearm' == $type && 1200 == $width && 1320 == $height )
  {
    // use the default measurements
  }
  // hip scans have a height of 1830 or 1930, so accept anything in between
  else if( 'hip' == $type && 1200 == $width && 1830 <= $height && $height <= 1930 )
  {
    // use the default measurements
  }
  else if( 'wbody' == $type && 1440 == $width && 1585 == $height )
  {
    // The label needs to be in the bottom-left, less wide and taller
    $label['x'] = 20;
    $label['y_offset'] = 390;
    $label['width'] = 400;
    $label['height'] = 260;
  }
  else
  {
    return sprintf( 'Unexected type/geometry: %s (%d, %d)', $type, $width, $height );
  }

  $redact_box_list = [
    [ // redact the Name, Patient ID, Identifier 2, and Postal Code information boxes
      $redact['x'],
      $redact['name_y'],
      // add 1 box
      $redact['x'] + $redact['box_width'],
      // add 4 box and 3 margins
      $redact['name_y'] - ( 4*$redact['box_height'] + 3*$redact['box_margin'] ),
    ],
    [ // redact the DOB information box
      $redact['x'],
      $redact['dob_y'],
      // add 1 box
      $redact['x'] + $redact['box_width'],
      // add 1 box
      $redact['dob_y'] - $redact['box_height'],
    ],
  ];

  $label_box = [
    $label['x'],
    $height - $label['y_offset'],
    $label['x'] + $label['width'],
    $height - $label['y_offset'] + $label['height'],
  ];

  $command = sprintf( 'convert %s', format_filename( $dicom_filename ) );

  // draw the redact boxes
  if( 0 < count( $redact_box_list ) )
  {
    $command .= ' -flip -fill "rgb(222, 222, 222)"';
    foreach( $redact_box_list as $box )
      $command .= sprintf( ' -draw "rectangle %d,%d,%d,%d"', $box[0], $box[1], $box[2], $box[3] );
    $command .= ' -flip';
  }

  // generate a unique disclaimer label
  if( !is_null( $label_box ) )
  {
    $command .= sprintf(
      ' -fill red -draw "rectangle %d,%d,%d,%d"',
      $label_box[0] - 4,
      $label_box[1] - 4,
      $label_box[2] + 2,
      $label_box[3] + 2
    );

    $command .= sprintf(
      ' -fill white -draw "rectangle %d,%d,%d,%d"',
      $label_box[0] - 2,
      $label_box[1] - 2,
      $label_box[2],
      $label_box[3]
    );

    $caption =
      // English
      'These results are for research purposes only and should not be used for clinical diagnosis or treatment.  '.
      'At the request of the participant, these results have been released to them.  '.
      'These results have not been checked for quality or interpreted.\n\n'.
      // French
      'Ces résultats sont utilisés à des fins de recherche seulement.  '.
      'Ils n’ont pas de valeur clinique ou diagnostique.  '.
      'Ils ont été communiqués au/à la participant·e à sa demande.  '.
      'Leur qualité n’a pas été vérifiée ni interprétée.';
    $command .= sprintf(
      ' \( '.
        '-background white '.
        '-fill red '.
        '-font Helvetica-Bold '.
        '-pointsize 14 '.
        '-size %dx%d '.
        '-interline-spacing 6 '.
        '-gravity NorthWest '.
        'caption:"%s" '.
      '\)',
      $label_box[2] - $label_box[0] - 3,
      $label_box[3] - $label_box[1] - 3,
      $caption
    );
    $command .= sprintf(
      ' -compose Over -geometry +%d+%d -composite',
      $label_box[0]+4,
      $label_box[1]+4
    );
  }

  $command .= sprintf( ' %s', format_filename( $image_filename ) );
  shell_exec( $command );
  return true;
}


////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// build the command argument details, then parse the passed args
$arguments = new arguments;
$arguments->set_description(
  "Generates a redacted JPEG representation of a DXA DICOM image\n".
  "This script will convert a DXA DICOM file to a JPEG file, redacting identifying information and adding ".
  "a warning the the image should not be used for clinical purposes."
);
$arguments->add_option( 't', 'type', 'The type of DXA scan being processed', true, 'unknown' );
$arguments->add_input( 'INPUT', 'The filename of the DXA DICOM file to convert' );
$arguments->add_input( 'OUTPUT', 'The filename of the generated JPEG file' );

$args = $arguments->parse_arguments( $argv );

$type = $args['option_list']['type'];
if( 0 == strlen( $type ) ) $type = 'unknown';
$dicom_filename = $args['input_list']['INPUT'];
$image_filename = $args['input_list']['OUTPUT'];

$result = create_dxa_for_participant( $type, $dicom_filename, $image_filename );
if( is_string( $result ) ) printf( "%s\n", $result );
