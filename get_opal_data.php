<?php
/**
 * This script downloads binary data from Opal directly to the raw/ directory.
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @date 2023-03-14
 */

require_once( 'settings.ini.php' );
require_once( 'src/common.php' );
require_once( 'src/arguments.class.php' );
require_once( 'src/opal_category_list.php' );

function get_side( $uid, $params )
{
  $opal_params = [
    'datasource' => $params['datasource'],
    'table' => $params['table'],
    'valueSet' => $uid,
    'variable' => $params['side']
  ];

  if( preg_match( '/Measure\./', $params['side'] ) )
  {
    $response = opal_send( $opal_params );
    if( !$response ) return NULL;

    $object = json_decode( $response );
    if( !property_exists( $object, 'values' ) ) return NULL;

    $side_list = [];
    foreach( $object->values as $value )
    {
      if( property_exists( $value, 'value' ) ) $side_list[] = strtolower( $value->value );
      else output( sprintf( 'Missing side data for %s', $uid ) );
    }
    return $side_list;
  }

  // not repeated
  $opal_params['value'] = NULL;
  $response = opal_send( $opal_params );
  return strtolower( $response );
}

function download_file( $uid, $base_dir, $params, &$count_list, $cenozo_db )
{
  $directory = sprintf( '%s/%s', $base_dir, $uid );

  // Ignore if the destination file already exists
  // Note that we use N=1 for repeated data, which means if the first file was downloaded then it
  // is assumed that all files were downloaded.
  $filename = sprintf(
    '%s/%s',
    $directory,
    str_replace( '<N>', 1, $params['filename'] )
  );
  if( is_file( $filename ) )
  {
    $count_list['skipped']++;
    return;
  }

  if( TEST_ONLY )
  {
    $count_list['download']++;
    return;
  }

  $opal_params = [
    'datasource' => $params['datasource'],
    'table' => $params['table'],
    'valueSet' => $uid,
    'variable' => $params['variable']
  ];

  if( !is_dir( $directory ) ) mkdir( $directory );

  // REPEATED IMAGE DATA /////////////////////////////////////////////////////////////////////////////////
  // if the variable name starts with "Measure." then this is a repeated variable (download one at a time)
  if( preg_match( '/Measure\./', $params['variable'] ) )
  {
    $first_output_filename = sprintf( '%s/%s', $directory, preg_replace( '/<N>/', 1, $params['filename'] ) );
    if( array_key_exists( 'pre_download_function', $params ) )
    {
      $params['pre_download_function']( $first_output_filename, $cenozo_db );
    }

    $response = opal_send( $opal_params );
    $object = NULL;
    $missing = false;
    if( !$response )
    {
      $missing = true;
    }
    else
    {
      $object = json_decode( $response );
      if( !property_exists( $object, 'values' ) ) $missing = true;
    }

    if( $missing )
    {
      // Create an empty file so that we know for the future that the data is missing.
      // If we don't do this then we will look for the file every time the script runs.
      file_put_contents( $first_output_filename, '' );
      $count_list['missing']++;
      return;
    }

    // if a side is included in the parameters then first get the side data from opal
    if( array_key_exists( 'side', $params ) )
    {
      $side_list = array_key_exists( 'side', $params ) ? get_side( $uid, $params ) : NULL;
      $side_total = [ 'left' => 0, 'right' => 0, 'unknown' => 0 ];
      $side_number = [ 'left' => 0, 'right' => 0, 'unknown' => 0 ];
      foreach( $side_list as $side )
      {
        if( 0 == strlen( $side ) ) $side = 'unknown';
        $side_total[$side]++;
      }
    }

    foreach( $object->values as $value )
    {
      // determine the opal paramters from the link
      $opal_params = [];
      $key = NULL;
      $index = NULL;
      foreach( explode( '/', $value->link ) as $part )
      {
        // ignore empty strings
        if( 0 == strlen( $part ) ) continue;

        if( in_array( $part, ['datasource', 'table', 'valueSet', 'variable'] ) )
        {
          $key = $part;
        }
        else if( preg_match( '/value\?pos=([0-9]+)/', $part, $matches ) )
        {
          $index = $matches[1];
          $opal_params['value'] = NULL;
          $opal_params['pos'] = $index;
        }
        else
        {
          $opal_params[$key] = $part;
        }
      }

      $output_filename = sprintf( '%s/%s', $directory, preg_replace( '/<N>/', $index+1, $params['filename'] ) );

      // the pre-download function is called for the first file above, so only do index value 1+
      if( 0 < $index && array_key_exists( 'pre_download_function', $params ) )
      {
        $params['pre_download_function']( $output_filename, $cenozo_db );
      }

      // now download the data for this iteration
      $response = opal_send( $opal_params );

      if( $response )
      {
        file_put_contents( $output_filename, $response );

        $count_list['download']++;

        $new_filename_list = [$output_filename];
        if( array_key_exists( 'post_download_function', $params ) )
        {
          $new_filename = $params['post_download_function']( $output_filename, $cenozo_db );
          if( is_string( $new_filename ) ) $new_filename_list[] = $new_filename;
          else if( is_array( $new_filename ) )
            $new_filename_list = array_merge( $new_filename_list, $new_filename );
        }

        // if a side exists then create a symlink with the side for all files that have been created
        if( array_key_exists( 'side', $params ) )
        {
          $side = array_key_exists( $index, $side_list ) ? $side_list[$index] : 'unknown';
          if( 0 == strlen( $side ) ) $side = 'unknown';
          $cwd = getcwd();

          foreach( $new_filename_list as $new_filename )
          {
            // only create a link if the file exists and isn't empty
            if( file_exists( $new_filename ) && 0 < filesize( $new_filename ) )
            {
              // increment the side index, but only when we get a new raw file (all others need the same number)
              if( '/data/raw' == substr( $new_filename, 0, 9 ) )
              {
                if( !array_key_exists( $side, $side_number ) ) $side_number[$side] = 0;
                $side_number[$side]++;
              }

              chdir( dirname( $new_filename ) );
              $link = preg_replace(
                // replace the part of the output filename without extension
                sprintf( '#^%s#', preg_replace( '#\..+$#', '', basename( $output_filename ) ) ),
                // with the part of the parameterized filename without extention, with <N> replaced by side
                preg_replace(
                  ['#\..+$#', '#<N>#'],
                  ['', 1 == $side_total[$side] ? $side : sprintf( '%s_%d', $side, $side_number[$side] )],
                  $params['filename']
                ),
                basename( $new_filename )
              );
              if( !file_exists( $link ) ) symlink( basename( $new_filename ), $link );
            }
          }

          chdir( $cwd );
        }
      }
      else
      {
        // create an empty file if it is missing (again, so we know in the future that it has been searched for)
        file_put_contents( $output_filename, '' );
        $count_list['missing']++;
      }
    }
  }

  // SINGLE IMAGE DATA /////////////////////////////////////////////////////////////////////////////////
  else // data is not repeated, so there's only one file to download
  {
    $output_filename = sprintf( '%s/%s', $directory, $params['filename'] );

    if( array_key_exists( 'pre_download_function', $params ) )
    {
      $params['pre_download_function']( $output_filename, $cenozo_db );
    }

    $opal_params['value'] = NULL;
    $response = opal_send( $opal_params );
    if( !$response )
    {
      // Create an empty file so that we know for the future that the data is missing.
      // If we don't do this then we will look for the file every time the script runs.
      file_put_contents( $output_filename, '' );
      $count_list['missing']++;
      return;
    }

    file_put_contents( $output_filename, $response );
    $count_list['download']++;

    $new_filename_list = [$output_filename];
    if( array_key_exists( 'post_download_function', $params ) )
    {
      $new_filename = $params['post_download_function']( $output_filename, $cenozo_db );
      if( is_string( $new_filename ) ) $new_filename_list[] = $new_filename;
      else if( is_array( $new_filename ) )
        $new_filename_list = array_merge( $new_filename_list, $new_filename );
    }

    // if a side exists then create a symlink with the side for all files that have been created
    if( array_key_exists( 'side', $params ) )
    {
      $side = get_side( $uid, $params );
      $cwd = getcwd();

      foreach( $new_filename_list as $new_filename )
      {
        // only create a link if the file exists and isn't empty
        if( file_exists( $new_filename ) && 0 < filesize( $new_filename ) )
        {
          chdir( dirname( $new_filename ) );
          $link = preg_replace( '/^([^.]+)/', sprintf( '$1_%s', $side ), $params['filename'] );
          if( !file_exists( $link ) ) symlink( basename( $new_filename ), $link );
        }
      }

      chdir( $cwd );
    }
  }
}


////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// build the command argument details, then parse the passed args
$arguments = new arguments;
$arguments->set_description( "Downloads data from Opal and stores it directly in the raw/ directory." );
$arguments->add_option( 'd', 'debug', 'Runs in test mode, no files will be affected.' );
$arguments->add_option( 'k', 'keep_files', 'Do not delete any files from the temporary directory.' );
$arguments->add_option( 'v', 'verbose', 'Shows more details when running the script.' );
$arguments->add_option( 'o', 'offset', 'Start the download at a particular offset', true, 0 );
$arguments->add_option( 'u', 'uid', 'Download data for a specific participant by UID', true, false );
$arguments->add_option( 'p', 'phase', 'The phase of the study the data belongs to', true, 1 );
$arguments->add_input( 'CATEGORY', 'Which data category to download (eg: cdtt, ecg, frax, etc...)' );

$args = $arguments->parse_arguments( $argv );

define( 'TEST_ONLY', array_key_exists( 'debug', $args['option_list'] ) );
define( 'KEEP_FILES', array_key_exists( 'keep_files', $args['option_list'] ) );
define( 'VERBOSE', array_key_exists( 'verbose', $args['option_list'] ) );
$initial_offset = $args['option_list']['offset'];
$uid = $args['option_list']['uid'];
$phase = $args['option_list']['phase'];
$category = $args['input_list']['CATEGORY'];

// make sure the category and phase is valid
if( !array_key_exists( $category, $category_list ) )
{
  fatal_error(
    sprintf(
      'No such category "%s"%sValid categories include: %s',
      $category,
      "\n",
      implode( ', ', array_keys( $category_list ) )
    ),
    6
  );
}

if( !array_key_exists( 'all', $category_list[$category] ) &&
    !array_key_exists( $phase, $category_list[$category] ) )
{
  fatal_error(
    sprintf( 'Category "%s" does not exist for phase "%d".', $category, $phase ),
    7
  );
}

$params = array_key_exists( 'all', $category_list[$category] )
        ? $category_list[$category]['all']
        : $category_list[$category][$phase];

// add the postfix to the datasource based on the phase input argument
if( 1 < $phase ) $params['datasource'] .= sprintf( '-f%d', $phase-1 );

// Download binary data from Opal
$count_list = [ 'skipped' => 0, 'download' => 0, 'missing' => 0 ];
$base_dir = sprintf( '%s/raw/clsa/%d/%s', DATA_DIR, $phase, $params['name'] );

// make sure the opal_enabled file exists
$opal_enabled_filename = sprintf( '%s/opal_enabled', __DIR__ );

if( !file_exists( $opal_enabled_filename ) )
{
  output( 'Not proceeding since no "opal_enabled" file exists.' );
  exit( 0 );
}

// only connect to the database if we have to
try
{
  $cenozo_db = $params['db_required'] ? get_cenozo_db() : NULL;
}
catch( \Exception $e )
{
  fatal_error( 'Failed to open required connection to cenozo database.', 8 );
}

if( $uid )
{
  output( sprintf( 'Downloading %s data from Opal to %s (for UID %s only)', $category, $base_dir, $uid ) );
  download_file( $uid, $base_dir, $params, $count_list, $cenozo_db );
}
else
{
  $response = opal_send( [
    'datasource' => $params['datasource'],
    'table' => $params['table'],
    'counts' => 'true'
  ] );

  $object = json_decode( $response );
  $total = $object->valueSetCount;
  $limit = 1000;
  output( sprintf( 'Downloading %s data from Opal to %s (%d rows)', $category, $base_dir, $total ) );

  for( $offset = $initial_offset; $offset < $total; $offset += $limit )
  {
    if( VERBOSE ) output( sprintf( 'Downloading files %d to %d', $offset, $offset + $limit - 1 ) );
    $response = opal_send( [
      'datasource' => $params['datasource'],
      'table' => $params['table'],
      'valueSets' => NULL,
      'limit' => $limit,
      'offset' => $offset
    ] );

    $object = json_decode( $response );
    foreach( $object->valueSets as $value_set )
    {
      if( !file_exists( $opal_enabled_filename ) ) break;
      download_file( $value_set->identifier, $base_dir, $params, $count_list, $cenozo_db );
    }

    if( !file_exists( $opal_enabled_filename ) )
    {
      // In order to stop downloading without leaving a half-downloaded file behind we stop
      // downloading if the opal_enabled file doesn't exist.
      output( 'Gracefully aborting since no "opal_enabled" file exists.' );
      break;
    }
  }
}

output( sprintf(
  'Done [%d valid, %d missing, %d skipped]',
  $count_list['download'],
  $count_list['missing'],
  $count_list['skipped']
) );

if( $cenozo_db ) $cenozo_db->close();

exit( 0 );
