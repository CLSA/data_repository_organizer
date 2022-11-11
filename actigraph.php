#!/usr/bin/php
<?php
require_once 'common.php';

$study_uid_lookup = get_study_uid_lookup( ACTIGRAPH_IDENTIFIER_NAME );

// Process all Actigraph files
// There is one file per participant having the filename format "<study_id>.gt3x"
output( sprintf( 'Processing actigraph files in "%s"', ACTIGRAPH_BASE_PATH ) );
$actigraph_file_count = 0;
foreach( glob( sprintf( '%s/*/*.gt3x', ACTIGRAPH_BASE_PATH ) ) as $filename )
{
  $matches = [];
  if( false === preg_match( '#/([^/]+) \(([0-9]{4}-[0-9]{2}-[0-9]{2})\)\.gt3x$#', $filename, $matches ) )
  {
    output( sprintf( 'Ignoring invalid actigraph file: "%s"', $filename ) );
    continue;
  }
  else if( 3 != count( $matches ) )
  {
    output( sprintf( 'Ignoring invalid actigraph file: "%s"', $filename ) );
    continue;
  }

  $study_id = strtoupper( trim( $matches[1] ) );
  $date = $matches[2];
  if( !array_key_exists( $study_id, $study_uid_lookup ) )
  {
    output( sprintf(
      'Cannot transfer actigraph file due to missing UID lookup: "%s" from file "%s"',
      $study_id,
      $dirname
    ) );
    continue;
  }
  $uid = $study_uid_lookup[$study_id];

  $destination_directory = sprintf(
    '%s/raw/%s/%s/actigraph/%s',
    DATA_REPOSITORY,
    ACTIGRAPH_STUDY_NAME,
    ACTIGRAPH_STUDY_PHASE,
    $uid
  );

  // make sure the directory exists (recursively)
  if( !is_dir( $destination_directory ) ) mkdir( $destination_directory, 0755, true );

  $destination = sprintf( '%s/%s.gt3x', $destination_directory, $date );
  $copy = TEST_ONLY ? true : copy( $filename, $destination );
  if( $copy )
  {
    if( VERBOSE ) output( sprintf( '"%s" => "%s"', $filename, $destination ) );
    if( !TEST_ONLY && !KEEP_FILES ) unlink( $filename );
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

exit( 0 );
