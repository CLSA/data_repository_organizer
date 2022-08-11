#!/usr/bin/php
<?php
ini_set( 'display_errors', '0' );
error_reporting( E_ALL | E_STRICT );

// function for writing to the log
function output( $message )
{
  printf( "%s> %s\n", date( 'Y-m-d (D) H:i:s' ), $message );
}

// move to the scripts root directory
chdir( dirname( __FILE__ ) );

require_once 'config.php';

// Read the id_lookup.csv file for converting study IDs to CLSA UIDs
$study_uid_lookup = [];
$handle = fopen( 'id_lookup.csv', 'r' );
while( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== FALSE )
{
  if( 'uid' != $data[0] ) $study_uid_lookup[$data[1]] = $data[0];
}
fclose( $handle );

// Process all Actigraph files
// There is one file per participant having the filename format "<study_id>.gt3x"
output( sprintf( 'Processing actigraph files in "%s"', ACTIGRAPH_BASE_PATH ) );
$actigraph_file_count = 0;
foreach( glob( sprintf( '%s/*.gt3x', ACTIGRAPH_BASE_PATH ) ) as $filename )
{
  $matches = [];
  if( false === preg_match( '#/(.*) \(([0-9]{4}-[0-9]{2}-[0-9]{2})\)\.gt3x$#', $filename, $matches ) )
  {
    output( sprintf( 'Ignoring invalid actigraph file: "%s"', $filename ) );
    continue;
  }

  $study_id = $matches[1];
  $date = $matches[2];
  if( !array_key_exists( $study_id, $study_uid_lookup ) )
  {
    output( sprintf(
      'Cannot transfer actigraph file due to missing UID lookup: "%s"',
      $filename
    ) );
    continue;
  }
  $uid = $study_uid_lookup[$study_id];

  $destination_directory = sprintf(
    '%s/raw/%s/%s/actigraph/%s',
    BASE_DATA_DIRECTORY,
    STUDY_NAME,
    STUDY_PHASE,
    $uid
  );

  // make sure the directory exists (recursively)
  if( !is_dir( $destination_directory ) ) mkdir( $destination_directory, 0755, true );

  $destination = sprintf( '%s/%s.gt3x', $destination_directory, $date );
  $copy = TEST_ONLY ? true : copy( $filename, $destination );
  if( $copy )
  {
    if( VERBOSE ) output( sprintf( '"%s" => "%s"', $filename, $destination ) );
    if( !TEST_ONLY ) unlink( $filename );
    $actigraph_file_count++;
  }
  else
  {
    output( sprintf( 'Failed to copy "%s" to "%s"', $filename, $destination ) );
  }
}
output( sprintf(
  'Done, %d files %stransferred',
  $actigraph_file_count,
  TEST_ONLY ? 'would be ' : ''
) );

// Process all Ticwatch files
// There are multiple files per participant, all found in the directory format "<study_id>/<serial>"
output( sprintf( 'Processing ticwatch directories in "%s"', TICWATCH_BASE_PATH ) );
$ticwatch_dir_count = 0;
$ticwatch_file_count = 0;
foreach( glob( sprintf( '%s/*/*', TICWATCH_BASE_PATH ), GLOB_ONLYDIR ) as $dirname )
{
  $matches = [];
  if( false === preg_match( '#/(.*)/(.*)$#', $dirname, $matches ) )
  {
    output( sprintf( 'Ignoring invalid ticwatch directory: "%s"', $filename ) );
    continue;
  }

  $study_id = $matches[1];
  if( !array_key_exists( $study_id, $study_uid_lookup ) )
  {
    output( sprintf(
      'Cannot transfer ticwatch directory due to missing UID lookup: "%s"',
      $dirname
    ) );
    continue;
  }
  $uid = $study_uid_lookup[$study_id];

  $destination_directory = sprintf(
    '%s/raw/%s/%s/ticwatch/%s',
    BASE_DATA_DIRECTORY,
    STUDY_NAME,
    STUDY_PHASE,
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
  if( !is_null( $latest_existing_date ) && $latest_date < $latest_existing_date )
  {
    output( sprintf( 'Ignoring files in %s as there already exists more recent files', $dirname ) );
    if( !TEST_ONLY ) array_map( 'unlink', glob( sprintf( '%s/*', $dirname ) ) );
  }
  else
  {
    // otherwise remove any existing files
    if( !TEST_ONLY ) array_map( 'unlink', glob( sprintf( '%s/*', $destination_directory ) ) );

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
        if( !TEST_ONLY ) unlink( $file_pair['source'] );
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

// find and remove all empty directories
function remove_dir( $dir )
{
  // first remove all empty sub directories
  foreach( glob( sprintf( '%s/*', $dir ), GLOB_ONLYDIR ) as $subdir ) remove_dir( $subdir );

  // now see if the directory is empty and remove it is it is
  if( 0 == count( glob( sprintf( '%s/*', $dir ) ) ) ) rmdir( $dir );
}

if( !TEST_ONLY )
{
  array_map( 'remove_dir', glob( sprintf( '%s/*', TICWATCH_BASE_PATH ), GLOB_ONLYDIR ) );
}
