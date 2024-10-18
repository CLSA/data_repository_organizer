<?php
/**
 * DATA_TYPE: dxa
 * 
 * Bone density DXA DICOM files
 */

namespace data_type;

require_once( __DIR__.'/base.class.php' );

class dxa extends base
{
  /**
   * Processes all dxa files
   */
  public static function process_files()
  {
    $base_dir = sprintf( '%s/%s', DATA_DIR, TEMPORARY_DIR );

    // Process all dxa recordings
    // DXA files are found in DXA1 (left/right hip) and DXA2 (lateral, spine, wbody, forearm)
    output( sprintf( 'Processing dxa files in "%s"', $base_dir ) );

    try
    {
      $cenozo_db = \util::get_cenozo_db();
    }
    catch( \Exception $e )
    {
      fatal_error( 'Failed to open required connection to cenozo database.', 12 );
    }

    // This data only comes from the Pine Site interview
    $file_count = 0;
    foreach( glob( sprintf( '%s/nosite/Follow-up * Site/DXA[12]/*/*', $base_dir ) ) as $filename )
    {
      $hip_re = '#nosite/Follow-up ([0-9]) Site/DXA1/([^/]+)/([LR])_HIP_DICOM.dcm$#';
      $lateral_re = '#nosite/Follow-up ([0-9]) Site/DXA2/([^/]+)/SEL_DICOM_(MEASURE|OT|PR).dcm$#';
      $spine_re = '#nosite/Follow-up ([0-9]) Site/DXA2/([^/]+)/SP_DICOM_([0-9]+).dcm$#';
      $wbody_re = '#nosite/Follow-up ([0-9]) Site/DXA2/([^/]+)/WB_DICOM_([0-9]+).dcm$#';
      $forearm_re = '#nosite/Follow-up ([0-9]) Site/DXA2/([^/]+)/FA_([LR])_DICOM.dcm$#';

      $phase = NULL;
      $uid = NULL;
      $question = NULL;
      $type = NULL;
      $side = NULL;
      $new_filename = NULL;

      $matches = [];
      if( preg_match( $hip_re, $filename, $matches ) )
      {
        // hip scan (left or right)
        $phase = $matches[1] + 1;
        $uid = $matches[2];
        $question = 'DXA1';
        $type = 'hip';
        $side = 'L' == $matches[3] ? 'left' : 'right';

        $new_filename = sprintf( 'dxa_hip_%s.dcm', $side );
      }
      else if( preg_match( $lateral_re, $filename, $matches ) )
      {
        // lateral scans (base, OT or PR)
        $phase = $matches[1] + 1;
        $uid = $matches[2];
        $question = 'DXA2';
        $type = 'lateral';
        $side = 'MEASURE' == $matches[3] ? 'none' : NULL;

        $new_filename = sprintf(
          'dxa_lateral%s.dcm',
          'MEASURE' == $matches[3] ? '' : ( '_'.strtolower( $matches[3] ) )
        );
      }
      else if( preg_match( $spine_re, $filename, $matches ) )
      {
        // spine scan
        $phase = $matches[1] + 1;
        $uid = $matches[2];
        $question = 'DXA2';
        $type = 'spine';
        $side = 'none';

        $new_filename = sprintf( 'dxa_spine.dcm', $destination_directory );
      }
      else if( preg_match( $wbody_re, $filename, $matches ) )
      {
        // wbody scan (BCA or BMD)
        $phase = $matches[1] + 1;
        $uid = $matches[2];
        $question = 'DXA2';
        $type = 'wbody';
        $side = '2' == $matches[3] ? 'none' : NULL;

        $new_filename = sprintf( 'dxa_wbody_%s.dcm', '1' == $matches[3] ? 'bmd' : 'bca' );
      }
      else if( preg_match( $forearm_re, $filename, $matches ) )
      {
        // forearm scan (left or right)
        $phase = $matches[1] + 1;
        $uid = $matches[2];
        $question = 'DXA2';
        $type = 'forearm';
        $side = 'L' == $matches[3] ? 'left' : 'right';

        $new_filename = sprintf( 'dxa_forearm_%s.dcm', $side );
      }
      else
      {
        self::move_from_temporary_to_invalid(
          $filename,
          sprintf( 'Invalid filename: "%s"', $filename )
        );
        continue;
      }

      $destination_directory = sprintf( '%s/%s/clsa/%s/dxa/%s', DATA_DIR, RAW_DIR, $phase, $uid );
      $destination = sprintf( '%s/%s', $destination_directory, $new_filename );
      if( self::process_file( $destination_directory, $filename, $destination ) )
      {
        // generate supplementary data from the xml file
        if( !TEST_ONLY ) self::generate_supplementary( $destination, $cenozo_db );
        $file_count++;

        // register the interview, exam and images in alder (if the alder db exists)
        if( !defined( 'ALDER_DB_DATABASE' ) ) continue;

        // only include hip/forearm (left/right) and lateral/wbody/spine (none)
        if( !(
          ( in_array( $type, ['hip', 'forearm'] ) && in_array( $side, ['left', 'right'] ) ) ||
          ( in_array( $type, ['lateral', 'wbody', 'spine'] ) && 'none' == $side )
        ) ) continue;

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
          $type,
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

    $cenozo_db->close();

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

  /**
   * Anonymizes an DXA DICOM file by removing identifying data
   * @param string $filename The name of the file to anonymize
   * @param string $organization An optional value to set the organization to (default is an empty string)
   * @param string $identifier An optional value to set the identifier to (default is an empty string)
   */
  public static function anonymize( $filename, $organization = '', $identifier = '', $debug = false )
  {
    $tag_list = [
      '0008,1010' => '',            // Station Name
      '0008,0080' => $organization, // Instituion Name
      '0008,1040' => 'NCC',         // Instituion Department Name
      '0008,1070' => '',            // Operators Name
      '0010,0010' => '',            // Patient Name
      '0010,1000' => '',            // Other Patient IDs
      '0018,1000' => '',            // Device Serial Number
      '0010,0020' => $identifier,   // Patient ID
      // Unknown Tags & Data
      '0019,1000' => NULL,
      '0023,1000' => NULL,
      '0023,1001' => NULL,
      '0023,1002' => NULL,
      '0023,1003' => NULL,
      '0023,1004' => NULL,
      '0023,1005' => NULL,
    ];

    $modify_list = [];
    foreach( $tag_list as $tag => $value )
    {
      $modify_list[] = sprintf( '-ma "(%s)%s"', $tag, is_null( $value ) ? '' : sprintf( '=%s', $value ) );
    }

    $command = sprintf(
      'dcmodify -nb -nrc -imt %s %s',
      implode( ' ', $modify_list ),
      \util::format_filename( $filename )
    );

    $result_code = 0;
    $output = NULL;
    $debug ? printf( "%s\n", $command ) : exec( $command, $output, $result_code );

    if( 0 < $result_code ) printf( implode( "\n", $output ) );
    return $result_code;
  }


  /**
   * Generates all supplementary files
   * 
   * This will generate jpeg versions of forearm, hip and wbody DICOM files for release to participants.
   * It will also create jpeg versions of forearm DICOM files for release to researchers.  It should only
   * be used as the post download function for dxa_hip, dxa_forearm and dxa_wbody_bmd DICOM files.
   */
  public static function generate_supplementary( $filename, $cenozo_db )
  {
    $new_filename_list = [];

    // need to create redacted participant versions of hip, forearm and BMD images
    $matches = [];
    if( preg_match( '#/([^/]+)/(dxa_hip|dxa_forearm|dxa_wbody_bmd)#', $filename, $matches ) )
    {
      $uid = $matches[1];
      $type = $matches[2];
      if( 'dxa_hip' == $type ) $type = 'hip';
      else if( 'dxa_forearm' == $type ) $type = 'forearm';
      else $type = 'wbody';
      $participant_image_filename = preg_replace(
        [sprintf( '#/%s/#', RAW_DIR ), '#\.dcm$#'],
        [sprintf( '/%s/', SUPPLEMENTARY_DIR ), '.participant.jpeg'],
        $filename
      );
      $researcher_image_filename = preg_replace(
        [sprintf( '#/%s/#', RAW_DIR ), '#\.dcm$#'],
        [sprintf( '/%s/', SUPPLEMENTARY_DIR ), '.jpeg'],
        $filename
      );
      $directory = dirname( $participant_image_filename );
      if( !is_dir( $directory ) ) mkdir( $directory, 0755, true );

      // get the Results Correspondence identifier
      if( !preg_match( '/^[A-Z][0-9][0-9][0-9][0-9][0-9][0-9]$/', $uid ) )
        throw new Exception( sprintf( 'Invalid UID "%s" found while creating participant DXA image.', $uid ) );
      $identifier = self::get_participant_identifier( $cenozo_db, 'Results Correspondence', $uid );

      // convert from dcm to participant jpeg
      $output = [];
      $result_code = NULL;
      exec(
        sprintf(
          '%s/../../bin/create_dxa_for_participant -t %s -i %s %s %s',
          __DIR__,
          $type,
          $identifier,
          $filename,
          $participant_image_filename
        ),
        $output,
        $result_code
      );
      if( 0 < $result_code )
      {
        // there was an error, so throw away any generated file
        if( file_exists( $participant_image_filename ) ) unlink( $participant_image_filename );
      }
      else
      {
        $new_filename_list[] = $participant_image_filename;
      }

      // convert forearms from dcm to researcher jpeg
      if( 'forearm' == $type )
      {
        $output = [];
        $result_code = NULL;
        exec(
          sprintf(
            '%s/../../bin/create_dxa_for_researcher -t %s %s %s',
            __DIR__,
            $type,
            $filename,
            $researcher_image_filename
          ),
          $output,
          $result_code
        );
        if( 0 < $result_code )
        {
          // there was an error, so throw away any generated file
          if( file_exists( $researcher_image_filename ) ) unlink( $researcher_image_filename );
        }
        else
        {
          $new_filename_list[] = $researcher_image_filename;
        }
      }
    }

    // return all new files created by this function
    return $new_filename_list;
  }
}
