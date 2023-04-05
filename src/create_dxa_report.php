<?php
require_once( 'common.php' );
require_once( 'arguments.class.php' );

/**
 * Generates a redacted JPEG representation of a DXA DICOM image
 * @param string $type The type of DXA scan
 * @param string $dicom_filename The input DXA DICOM image filename
 * @param string $image_filename The output JPEG image filename
 */
function create_dxa_report( $type, $dicom_filename, $image_filename )
{
  $redact_box_list = [];
  $label_box = NULL;
  if( 'forearm' == $type )
  {
    $redact_box_list = [[170, 1204, 248, 1178], [170, 973, 248, 948]];
    $label_box = [62, 1261, 1138, 1375];
  }
  else if( 'hip' == $type )
  {
    $redact_box_list = [[170, 1614, 248, 1589], [170, 1383, 248, 1358]];
    $label_box = [62, 1671, 1138, 1785];
  }
  else if( 'wbody' == $type )
  {
    $redact_box_list = [[170, 1545, 248, 1520], [170, 1314, 248, 1289]];
    $label_box = [62, 1195, 426, 1504];
  }

  $command = sprintf( 'convert %s', $dicom_filename );

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
      'These tests were conducted for research purposes only; these results should not be used as the basis '.
      'for clinical diagnosis or treatment.  These results have been released to the participant at their '.
      'request.\n\n'.
      'Les mesures effectuées au Site de collecte de données ne sont utilisées qu’à des fins de recherche. '.
      'Les résultats qui en découlent n’ont pas de valeur diagnostique ou thérapeutique.  Les résultats ont '.
      'été envoyés au participant à sa demande.';
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
      $label_box[2] - $label_box[0]+1,
      $label_box[3] - $label_box[1]+1,
      $caption
    );
    $command .= sprintf(
      ' -compose Over -geometry +%d+%d -composite',
      $label_box[0],
      $label_box[1]
    );
  }

  $command .= sprintf( ' %s', $image_filename );
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
$dicom_filename = $args['input_list']['INPUT'];
$image_filename = $args['input_list']['OUTPUT'];

create_dxa_report( $type, $dicom_filename, $image_filename );
