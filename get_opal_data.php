<?php
require_once 'src/common.php';
require_once( 'src/opal_category_list.php' );

function download_file( $uid, $base_dir, $params, &$count_list )
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

  if( !is_dir( $directory ) ) mkdir( $directory );

  // if the variable name starts with "Measure." then this is a repeated variable (download one at a time)
  if( preg_match( '/Measure\./', $params['variable'] ) )
  {
    $response = opal_send( [
      'datasource' => $params['datasource'],
      'table' => $params['table'],
      'valueSet' => $uid,
      'variable' => $params['variable']
    ] );

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
      file_put_contents(
        sprintf( '%s/%s', $directory, preg_replace( '/<N>/', 1, $params['filename'] ) ),
        ''
      );
      
      $count_list['missing']++;
      return;
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

      // now download the data for this iteration
      $response = opal_send( $opal_params );

      if( $response )
      {
        file_put_contents(
          sprintf( '%s/%s', $directory, preg_replace( '/<N>/', $index+1, $params['filename'] ) ),
          $response
        );
        $count_list['download']++;
      }
      else
      {
        // create an empty file if it is missing (again, so we know in the future that it has been searched for)
        file_put_contents(
          sprintf( '%s/%s', $directory, preg_replace( '/<N>/', $index+1, $params['filename'] ) ),
          ''
        );
        $count_list['missing']++;
      }
    }
  }
  else
  {
    $output_filename = sprintf( '%s/%s', $directory, $params['filename'] );

    if( array_key_exists( 'pre_download_function', $params ) )
    {
      $params['pre_download_function']( $output_filename );
    }

    $response = opal_send( [
      'datasource' => $params['datasource'],
      'table' => $params['table'],
      'valueSet' => $uid,
      'variable' => $params['variable'],
      'value' => NULL
    ] );

    if( $response )
    {
      file_put_contents( $output_filename, $response );
      $count_list['download']++;

      if( array_key_exists( 'post_download_function', $params ) )
      {
        $params['post_download_function']( $output_filename );
      }
    }
    else
    {
      // create an empty file if it is missing (again, so we know in the future that it has been searched for)
      file_put_contents(
        sprintf( '%s/%s', $directory, $params['filename'] ),
        ''
      );
      $count_list['missing']++;
    }
  }
}

$possible_category_list = array_keys( $category_list );

if(
  3 > $argc ||
  !preg_match( '/^[0-9]$/', $argv[1] ) ||
  !in_array( $argv[2], $possible_category_list )
) {
  file_put_contents(
    'php://stderr',
    "Usage: php get_opal_data.php <phase> <category> (<offset|uid>)\n".
    "       where <phase> is the rank of the phase (1 is baseline, 2 is F1, etc)\n".
    "       and <category> must be one of the following:\n".
    "         ".join( "\n         ", $possible_category_list )."\n".
    "       and <offset> is an optional parameter that will start the download with the given offset\n".
    "       and <uid> is the CLSA ID of a specific participant to download (must be A000000 format)\n"
  );
  exit;
}

$phase = $argv[1];
$category = $argv[2];
$initial_offset = 0;
$initial_uid = NULL;
if( 4 == $argc )
{
  if( preg_match( '/^[A-Z][0-9]{6}$/', $argv[3] ) )
  {
    $initial_uid = $argv[3];
  }
  else
  {
    $initial_offset = $argv[3];
  }
}

if( !array_key_exists( 'all', $category_list[$argv[2]] ) &&
    !array_key_exists( $phase, $category_list[$argv[2]] ) )
{
  fatal_error(
    sprintf(
      'Category "%s" does not exist for phase "%d".',
      $category,
      $phase
    ),
    6
  );
}

$params = array_key_exists( 'all', $category_list[$argv[2]] )
        ? $category_list[$argv[2]]['all']
        : $category_list[$argv[2]][$phase];

// add the postfix to the datasource based on the phase input argument
if( 1 < $phase ) $params['datasource'] .= sprintf( '-f%d', $phase-1 );

// Download binary data from Opal
$count_list = array(
  'skipped' => 0,
  'download' => 0,
  'missing' => 0
);

$base_dir = sprintf(
  '%s/raw/clsa/%d/%s',
  DATA_DIR,
  $phase,
  $params['name']
);

if( !is_null( $initial_uid ) )
{
  output( sprintf( 'Downloading %s data from Opal to %s (for UID %s only)', $category, $base_dir, $initial_uid ) );
  download_file( $initial_uid, $base_dir, $params, $count_list );
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
      download_file( $value_set->identifier, $base_dir, $params, $count_list );
  }
}

output( sprintf(
  'Done [%d valid, %d empty, %d skipped]',
  $count_list['download'],
  $count_list['missing'],
  $count_list['skipped']
) );

exit( 0 );
