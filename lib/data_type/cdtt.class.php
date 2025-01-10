<?php
/**
 * DATA_TYPE: cdtt
 * 
 * Bone density DXA DICOM files
 */

namespace data_type;

require_once( __DIR__.'/base.class.php' );

class cdtt extends base
{
  /**
   * Processes all cdtt files
   */
  public static function process_files()
  {
    $base_dir = sprintf( '%s/%s', DATA_DIR, TEMPORARY_DIR );

    // Process all CDTT data
    // There is a single file named cdtt.xlsx for each participant
    output( sprintf( 'Processing cdtt files in "%s"', $base_dir ) );

    // This data only comes from the Pine Site interview
    $processed_uid_list = [];
    $file_count = 0;
    foreach( glob( sprintf( '%s/nosite/Follow-up * Site/CDTT/*/*', $base_dir ) ) as $filename )
    {
      $re = '#nosite/Follow-up ([0-9]) Site/CDTT/([^/]+)/cdtt\.xlsx$#';
      $matches = [];
      if( !preg_match( $re, $filename, $matches ) )
      {
        self::move_from_temporary_to_invalid(
          $filename,
          sprintf( 'Invalid filename: "%s"', $filename )
        );
        continue;
      }

      $phase = $matches[1] + 1;
      $uid = $matches[2];

      $destination_directory = sprintf(
        '%s/%s/clsa/%s/cdtt/%s',
        DATA_DIR,
        RAW_DIR,
        $phase,
        $uid
      );
      $destination = sprintf( '%s/result_file.xls', $destination_directory );

      if( self::process_file( $destination_directory, $filename, $destination ) )
      {
        $processed_uid_list[] = $uid;
        $file_count++;
      }
    }

    // now remove all empty directories
    foreach( glob( sprintf( '%s/nosite/*/*/*', $base_dir ) ) as $dirname )
    {
      if( is_dir( $dirname ) ) self::remove_dir( $dirname );
    }

    output( sprintf(
      'Done, %d files from %d participants %stransferred',
      $file_count,
      count( array_unique( $processed_uid_list ) ),
      TEST_ONLY ? 'would be ' : ''
    ) );
  }
}
