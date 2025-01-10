<?php
/**
 * DATA_TYPE: retinal
 * 
 * Bone density DXA DICOM files
 */

namespace data_type;

require_once( __DIR__.'/base.class.php' );

class retinal extends base
{
  /**
   * Processes all retinal files
   */
  public static function process_files()
  {
    $base_dir = sprintf( '%s/%s', DATA_DIR, TEMPORARY_DIR );

    // Process all retinal data
    // There is either one or two files per side per participant
    output( sprintf( 'Processing retinal files in "%s"', $base_dir ) );

    if( defined( 'ALDER_DB_DATABASE' ) )
    {
      try
      {
        $cenozo_db = \util::get_cenozo_db();
      }
      catch( \Exception $e )
      {
        fatal_error( 'Failed to open required connection to cenozo database.', 12 );
      }
    }

    // This data only comes from the Pine Site interview
    $processed_uid_list = [];
    $file_count = 0;
    foreach( glob( sprintf( '%s/nosite/Follow-up * Site/RET_[RL]/*/*', $base_dir ) ) as $filename )
    {
      $re = '#nosite/Follow-up ([0-9]) Site/(RET_[RL])/([^/]+)/(EYE|OCT)_(RIGHT|LEFT)\.(jpg|dcm)$#';
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
      $question = $matches[2];
      $uid = $matches[3];
      $image_type = $matches[4];
      $side = strtolower( $matches[5] );
      $extension = strtolower( $matches[6] );
      if( 'jpg' == $extension ) $extension = 'jpeg';

      $destination_directory = sprintf(
        '%s/%s/clsa/%s/retinal/%s',
        DATA_DIR,
        RAW_DIR,
        $phase,
        $uid
      );
      $new_filename = sprintf(
        '%s_%s.%s',
        'EYE' == $image_type ? 'retinal' : 'oct',
        $side,
        $extension
      );
      $destination = sprintf( '%s/%s', $destination_directory, $new_filename );

      if( self::process_file( $destination_directory, $filename, $destination ) )
      {
        $processed_uid_list[] = $uid;
        $file_count++;

        // only write alder data for eye images
        if( 'EYE' == $image_type )
        {
          static::write_data_to_alder(
            $cenozo_db,
            $phase,
            $uid,
            $question,
            'retinal',
            $side,
            $new_filename
          );
        }
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
