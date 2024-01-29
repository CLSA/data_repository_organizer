<?php
require_once( 'common.php' );
require_once( 'arguments.class.php' );

/**
 * Creates an array of relevant numerical values (in cIMT SR report DICOM files
 * @param string $dcm_filename The input cIMT SR DICOM report filename
 */

/**
 * The expected array returned by this function is as follows:
 * [
 *   "Average": array of 1 to 18 floats, usually 4
 *   "Max": array of 1 to 18 floats, usually 4
 *   "Min": array of 1 to 18 floats, usually 4
 *   "SD": array of 1 to 18 floats, usually 4
 *   "nMeas": array of 1 to 18 floats, usually 4
 * ]
 * 
 * All values refer to "IMT Posterior"
 */
function parse_cimt_sr_file( $dcm_filename )
{
  $output = NULL;
  $result_code = NULL;
  exec(
    sprintf(
      'dcmdump +T %s | strings | grep "CodeMeaning\|NumericValue"',
      $dcm_filename
    ),  
    $output,
    $result_code
  );  

  if( 0 != $result_code )
  {
    fatal_error( sprintf(
      'Unable to parse DCM report file "%s" (error code %d)',
      $dcm_filename,
      $result_code
    ) );
  }

  $data = [ 'Average' => [], 'Max' => [], 'Min' => [], 'SD' => [], 'nMeas' => [] ];
  $key = NULL;
  foreach( $output as $line )
  {
    // data headings are always 8 children deep and named "CodeMeaning"
    $matches = []; 
    if( preg_match( '/^\| \| \| \| \| \| \| \| CodeMeaning +\[IMT Posterior (.+)\]/', $line, $matches ) ) 
    {   
      $key = $matches[1];
      continue;
    }   

    // data values are always 9 children deep and named "NumericValue"
    $matches = []; 
    if( preg_match( '/^\| \| \| \| \| \| \| \| NumericValue +\[(.+)\]/', $line, $matches ) ) 
    {   
      if( !is_null( $key ) ) 
      {   
        $data[$key][] = $matches[1];
        $key = NULL;
      }   
    }   
  }

  return $data;
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// build the command argument details, then parse the passed args
$arguments = new arguments;
$arguments->set_description(
  "Parses a cIMT SR report DICOM file for all IMG Posterior values.  One CSV line will be printed for each ".
  "iteration of IMT posterior values (usually 4, but there may be anywhere from 1 to 18 iterations).  Each ".
  "line will contain 6 values: rank, Average, Max, Min, SD, and nMeas."
);
$arguments->add_input( 'INPUT', 'The filename of the cIMT SR DICOM report file to parse' );

$args = $arguments->parse_arguments( $argv );

$dicom_filename = $args['input_list']['INPUT'];

$result = parse_cimt_sr_file( $dicom_filename );
if( 0 < count( $result ) )
{
  // get the maximum number of iterations
  $iterations = 0;
  foreach( $result as $category => $array )
  {
    $count = count( $array );
    if( $iterations < $count ) $iterations = $count;
  }

  for( $i = 0; $i < $iterations; $i++ )
  {
    $row = [$i+1];
    foreach( $result as $category => $array ) $row[] = array_key_exists( $i, $array ) ? $array[$i] : '';
    printf( "%s\n", implode( ',', $row ) );
  }
}
