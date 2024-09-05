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
    // There is one file per side per participant
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
    $file_count = 0;
    foreach( glob( sprintf( '%s/nosite/Follow-up * Site/RET_[RL]/*/*', $base_dir ) ) as $filename )
    {
      $re = '#nosite/Follow-up ([0-9]) Site/(RET_[RL])/([^/]+)/EYE_(RIGHT|LEFT)\.jpg$#';
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
      $side = strtolower( $matches[4] );
      $destination_directory = sprintf(
        '%s/%s/clsa/%s/retinal/%s',
        DATA_DIR,
        RAW_DIR,
        $phase,
        $uid
      );
      $new_filename = sprintf( 'retinal_%s.jpeg', $side );
      $destination = sprintf( '%s/%s', $destination_directory, $new_filename );

      if( self::process_file( $destination_directory, $filename, $destination ) )
      {
        $file_count++;

        // register the interview, exam and images in alder (if the alder db exists)
        if( !defined( 'ALDER_DB_DATABASE' ) ) continue;

        $metadata = static::get_pine_metadata( $cenozo_db, $phase, $uid, $question );
        if( is_null( $metadata ) ) continue;

        $obj = json_decode( $metadata['value'] );
        if(
          !is_object( $obj ) ||
          !property_exists( $obj, 'session' ) ||
          !property_exists( $obj->session, 'barcode' ) ||
          !property_exists( $obj->session, 'interviewer' ) ||
          !property_exists( $obj->session, 'end_time' )
        ) {
          output( sprintf( 'No result data in %s metadata from Pine for %s', $question, $uid ) );
          continue;
        }

        $interview_id = static::assert_alder_interview(
          $cenozo_db,
          $metadata['participant_id'],
          $metadata['study_phase_id'],
          $metadata['site_id'],
          $obj->session->barcode,
          $metadata['start_datetime'],
          $metadata['end_datetime']
        );
        if( false === $interview_id )
        {
          output( sprintf( 'Unable to read or create interview data from Alder for %s', $uid ) );
          continue;
        }

        $exam_id = static::assert_alder_exam(
          $cenozo_db,
          $interview_id,
          'retinal',
          $side,
          $obj->session->interviewer,
          preg_replace( '/(.+)T(.+)\.[0-9]+Z/', '\1 \2', $obj->session->end_time ) // convert to YYYY-MM-DD HH:mm:SS
        );
        if( false === $exam_id )
        {
          output( sprintf( 'Unable to read or create exam data from Alder for %s', $uid ) );
          continue;
        }

        if( false === static::assert_alder_image( $cenozo_db, $exam_id, $new_filename ) )
        {
          output( sprintf( 'Unable to read or create image "%s" from Alder for %s', $new_filename, $uid ) );
        }
      }
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
