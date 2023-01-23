<?php
require_once 'src/common.php';

$base_dir = sprintf( '%s/%s', DATA_DIR, TEMPORARY_DIR );
$study_uid_lookup = get_study_uid_lookup( ACTIGRAPH_IDENTIFIER_NAME );

// Process all Actigraph files
// Each site has their own directory, and in each site directory there are sub-directories for
// each modality (actigraph, ticwatch, etc).  Within the actigraph directory there is one file
// per participant named after the participant's study_id and the date of the data:
// For example: "temporary/XXX/actigraph/<study_id> <date>.gt3x"
output( sprintf( 'Processing actigraph files in "%s"', $base_dir ) );
$file_count = 0;
foreach( glob( sprintf( '%s/[A-Z][A-Z][A-Z]/actigraph/*', $base_dir ) ) as $filename )
{
  $matches = [];
  if( !preg_match( '#/([^/]+) \(([0-9]{4}-[0-9]{2}-[0-9]{2})\)\.gt3x$#', $filename, $matches ) )
  {
    if( VERBOSE ) output( sprintf(
      'Cannot transfer actigraph file, "%s", invalid format.',
      $filename
    ) );
    if( !TEST_ONLY && !KEEP_FILES ) move_from_temporary_to_invalid( $filename );
    continue;
  }

  $study_id = strtoupper( trim( $matches[1] ) );
  $date = str_replace( '-', '', $matches[2] );
  if( !array_key_exists( $study_id, $study_uid_lookup ) )
  {
    output( sprintf(
      'Cannot transfer actigraph file due to missing UID lookup for study ID "%s"',
      $study_id
    ) );
    if( !TEST_ONLY && !KEEP_FILES ) move_from_temporary_to_invalid( $filename );
    continue;
  }
  $uid = $study_uid_lookup[$study_id];

  $destination_directory = sprintf(
    '%s/raw/%s/%s/actigraph/%s',
    DATA_DIR,
    ACTIGRAPH_STUDY_NAME,
    ACTIGRAPH_STUDY_PHASE,
    $uid
  );

  // make sure the directory exists (recursively)
  if( !TEST_ONLY && !is_dir( $destination_directory ) ) mkdir( $destination_directory, 0755, true );

  $destination = sprintf( '%s/%s.gt3x', $destination_directory, $date );
  $copy = TEST_ONLY ? true : copy( $filename, $destination );
  if( $copy )
  {
    if( VERBOSE ) output( sprintf( '"%s" => "%s"', $filename, $destination ) );
    if( !TEST_ONLY && !KEEP_FILES ) unlink( $filename );
    $file_count++;
  }
  else
  {
    output( sprintf(
      'Failed to copy "%s" to "%s"',
      $filename,
      $destination
    ) );
    if( !TEST_ONLY && !KEEP_FILES ) move_from_temporary_to_invalid( $filename );
  }
}

output( sprintf(
  'Done, %d files %stransferred',
  $file_count,
  TEST_ONLY ? 'would be ' : ''
) );

exit( 0 );
