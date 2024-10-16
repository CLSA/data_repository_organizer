<?php
/**
 * DATA_TYPE: choice_rt
 * 
 * Bone density DXA DICOM files
 */

namespace data_type;

require_once( __DIR__.'/base.class.php' );

class choice_rt extends base
{
  /**
   * Processes all choice_rt files
   */
  public static function process_files()
  {
    $base_dir = sprintf( '%s/%s', DATA_DIR, TEMPORARY_DIR );

    // Process all choice reaction test data
    // There is a single file named data.csv for each participant
    output( sprintf( 'Processing choice_rt files in "%s"', $base_dir ) );

    // This data only comes from the Pine Site interview
    $file_count = 0;
    foreach( glob( sprintf( '%s/nosite/Follow-up * Site/CRT/*/*', $base_dir ) ) as $filename )
    {
      $re = '#nosite/Follow-up ([0-9]) Site/CRT/([^/]+)/data\.csv$#';
      $matches = [];

      if( !preg_match( $re, $filename, $matches ) )
      {
        self::move_from_temporary_to_invalid(
          $filename,
          sprintf( 'Invalid filename: "%s"', $filename )
        );
        continue;
      }

      $destination_directory = sprintf(
        '%s/%s/clsa/%s/choice_rt/%s',
        DATA_DIR,
        RAW_DIR,
        $matches[1] + 1, // phase
        $matches[2] // UID
      );
      $destination = sprintf( '%s/result_file.csv', $destination_directory );

      if( self::process_file( $destination_directory, $filename, $destination ) ) $file_count++;
    }

    // now remove all empty directories
    foreach( glob( sprintf( '%s/nosite/*/*/*', $base_dir ) ) as $dirname )
    {
      if( is_dir( $dirname ) ) self::remove_dir( $dirname );
    }

    output( sprintf(
      'Done, %d files %stransferred',
      $file_count,
      TEST_ONLY ? 'would be ' : ''
    ) );
  }
}
