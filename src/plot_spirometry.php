<?php
error_reporting( E_ALL | E_STRICT );
require_once( 'common.php' );
require_once( 'arguments.class.php' );
require_once( '/usr/local/lib/PHPlot/src/phplot.php' );

define( 'VERSION', '1.0' );

/**
 * Prints datetime-indexed messages to stdout
 * @param string $message
 */
function out( $message )
{
  if( VERBOSE ) printf( "%s\n", $message );
}

/**
 * Generates a plot of Spirometry data in volume and flow files
 * @param string $input_directory The name of the text file containing volume data
 * @param string $output_filename The name of the output JPEG file
 */
function plot( $input_directory, $output_filename )
{
  $input_directory = preg_replace( '#/$#', '', $input_directory );

  $file_list = [];
  foreach( glob( sprintf( '%s/spirometry_volume_*.txt', $input_directory ) ) as $volume_filename )
  {
    $matches = [];
    preg_match( '/spirometry_volume_(.+).txt/', $volume_filename, $matches );
    $iteration = $matches[1];
    $flow_filename = sprintf( '%s/spirometry_flow_%d.txt', $input_directory, $iteration );

    if( file_exists( $flow_filename ) )
      $file_list[$iteration] = ['volume' => $volume_filename, 'flow' => $flow_filename];
  }

  $data = [];
  $total_iterations = count( $file_list );
  foreach( $file_list as $iteration => $file_pair )
  {
    $volume_file = @file_get_contents( $file_pair['volume'] );
    if( false === $volume_file )
    {
      fatal_error( sprintf( 'Cannot read volume file "%s"', $file_pair['volume'] ), 10 );
    }

    $flow_file = @file_get_contents( $file_pair['flow'] );
    if( false === $flow_file )
    {
      fatal_error( sprintf( 'Cannot read flow file "%s"', $file_pair['flow'] ), 11 );
    }

    $volume_data = explode( ' ', $volume_file );
    $flow_data = explode( ' ', $flow_file );

    $volume_count = count( $volume_data );
    $flow_count = count( $flow_data );

    if( 0 == $volume_count )
    {
      fatal_error( 'No data found in volume file', 12 );
    }
    else if( 0 == $flow_count )
    {
      fatal_error( 'No data found in flow file', 13 );
    }
    else if( $volume_count != $flow_count )
    {
      fatal_error(
        sprintf(
          'Volume and flow data mismatch (volume: %d, flow: %d)',
          $volume_count,
          $flow_count
        ),
        14
      );
    }

    $legend_list[] = sprintf( 'Iteration #%d', $iteration );
    foreach( $volume_data as $index => $volume )
    {
      $point = ['', $volume];
      for( $i = 0; $i < $total_iterations; $i++ ) $point[] = $i == $iteration ? $flow_data[$index] : null;
      $data[] = $point;
    }
  }

  $plot = new \PHPlot\PHPlot\PHPlot( 1280, 720 );
  $black = imagecolorresolve( $plot->img, 0, 0, 0 );
  $plot->SetIsInline( true );
  $plot->SetOutputFile( $output_filename );
  $plot->SetImageBorderType( 'plain' );
  $plot->SetTitle( 'Spirometry Flow (L/s) vs Volume (L)' );
  $plot->SetFont( 'title', 4 );
  $plot->SetFont( 'legend', 3 );
  $plot->SetFont( 'generic', 3 );
  $plot->SetFont( 'x_label', 3 );
  $plot->SetFont( 'y_label', 3 );
  $plot->SetFont( 'x_title', 3 );
  $plot->SetFont( 'y_title', 3 );

  $plot->SetPlotType( 'lines' );
  $plot->SetDataType( 'data-data' );
  $plot->SetLegend( $legend_list );
  $plot->SetLineWidth( 2 );
  $plot->SetDataValues( $data );
  $plot->DrawGraph();
}


////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// build the command argument details, then parse the passed args
$arguments = new arguments;
$arguments->set_version( VERSION );
$arguments->set_description(
  "Plots Spirometry raw data found in space-delimited txt files, generating the plot as a PNG file."
);
$arguments->add_option( 'd', 'debug', 'Outputs the script\'s commands without executing them' );
$arguments->add_option( 'v', 'verbose', 'Shows more details when running the script' );

$arguments->add_input(
  'INPUT',
  'The directory containing spirometry_volume_*.txt and spirometry_volume_*.txt files'
);
$arguments->add_input( 'OUTPUT', 'The name of the output PNG file' );

$args = $arguments->parse_arguments( $argv );

define( 'VERBOSE', array_key_exists( 'verbose', $args['option_list'] ) );

$input_directory = $args['input_list']['INPUT'];
$output_filename = $args['input_list']['OUTPUT'];

out( sprintf( 'Plotting Spirometry data in "%s"', $input_directory ) );
plot( $input_directory, $output_filename );
