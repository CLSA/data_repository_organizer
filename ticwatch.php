#!/usr/bin/php
<?php
require_once 'common.php';

$study_uid_lookup = get_study_uid_lookup( TICWATCH_IDENTIFIER_NAME );

// Process all Ticwatch files
// Each site has their own directory, and in each site directory there are multiple files per participant,
// all found in the directory format "<site>/<study_id>/<serial>"
output( sprintf( 'Processing ticwatch directories in "%s"', TICWATCH_BASE_PATH ) );
$ticwatch_dir_count = 0;
$ticwatch_file_count = 0;
foreach( glob( sprintf( '%s/*/*/*', TICWATCH_BASE_PATH ), GLOB_ONLYDIR ) as $dirname )
{
  $matches = [];
  if( false === preg_match( '#/([^/]+)/([^/]+)$#', $dirname, $matches ) )
  {
    output( sprintf( 'Ignoring invalid ticwatch directory: "%s"', $filename ) );
    continue;
  }

  $study_id = strtoupper( trim( $matches[1] ) );
  if( !array_key_exists( $study_id, $study_uid_lookup ) )
  {
    output( sprintf(
      'Cannot transfer ticwatch directory due to missing UID lookup: "%s" from file "%s"',
      $study_id,
      $dirname
    ) );
    continue;
  }
  $uid = $study_uid_lookup[$study_id];

  $destination_directory = sprintf(
    '%s/raw/%s/%s/ticwatch/%s',
    DATA_REPOSITORY,
    TICWATCH_STUDY_NAME,
    TICWATCH_STUDY_PHASE,
    $uid
  );

  // make sure the directory exists (recursively)
  if( !is_dir( $destination_directory ) ) mkdir( $destination_directory, 0755, true );

  // make a list of all files to be copied and note the latest date
  $latest_date = NULL;
  $file_pair_list = [];
  foreach( glob( sprintf( '%s/*', $dirname ) ) as $filename )
  {
    $destination_filename = substr( $filename, strrpos( $filename, '/' )+1 );

    // remove any identifiers from the filename
    $destination_filename = preg_replace(
      sprintf( '/^%s_/', $study_id ),
      '',
      $destination_filename
    );

    // see if there is a date in the filename that comes after the latest date
    if( preg_match( '#_(20[0-9]{6})\.#', $destination_filename, $matches ) )
    {
      $date = intval( $matches[1] );
      if( is_null( $latest_date ) || $date > $latest_date ) $latest_date = $date;
    }

    $destination = sprintf( '%s/%s', $destination_directory, $destination_filename );

    $file_pair_list[] = [
      'source' => $filename,
      'destination' => $destination
    ];
  }

  // only copy files if they are not older than any files in the destination directory
  $latest_existing_date = NULL;
  foreach( glob( sprintf( '%s/*', $destination_directory ) ) as $filename )
  {
    $existing_filename = substr( $filename, strrpos( $filename, '/' )+1 );
    
    // see if there is a date in the filename that comes after the latest date
    if( preg_match( '#_(20[0-9]{6})\.#', $existing_filename, $matches ) )
    {
      $date = intval( $matches[1] );
      if( is_null( $latest_existing_date ) || $date > $latest_existing_date )
      {
        $latest_existing_date = $date;
      }
    }
  }

  // delete the local files if the are not newer than existing files
  if( !is_null( $latest_existing_date ) && $latest_date <= $latest_existing_date )
  {
    output( sprintf( 'Ignoring files in %s as there already exists more recent files', $dirname ) );
    if( !TEST_ONLY && !KEEP_FILES ) array_map( 'unlink', glob( sprintf( '%s/*', $dirname ) ) );
  }
  else
  {
    // otherwise remove any existing files
    if( !TEST_ONLY && !KEEP_FILES ) array_map( 'unlink', glob( sprintf( '%s/*', $destination_directory ) ) );

    // then copy the local files to their destinations (deleting them as we do)
    $success = true;
    foreach( $file_pair_list as $file_pair )
    {
      $copy = TEST_ONLY ? true : copy( $file_pair['source'], $file_pair['destination'] );
      if( $copy )
      {
        if( VERBOSE )
        {
          output( sprintf( '"%s" => "%s"', $file_pair['source'], $file_pair['destination'] ) );
        }
        if( !TEST_ONLY && !KEEP_FILES ) unlink( $file_pair['source'] );
        $ticwatch_file_count++;
      }
      else
      {
        output( sprintf(
          'Failed to copy "%s" to "%s"',
          $file_pair['source'],
          $file_pair['destination']
        ) );
        $success = false;
      }
    }
  }
  $ticwatch_dir_count++;
}
output( sprintf(
  'Done, %d files %stransferred from %d directories',
  $ticwatch_file_count,
  TEST_ONLY ? 'would be ' : '',
  $ticwatch_dir_count
) );

if( !TEST_ONLY && !KEEP_FILES )
{
  array_map( 'remove_dir', glob( sprintf( '%s/*', TICWATCH_BASE_PATH ), GLOB_ONLYDIR ) );
}

exit( 0 );
