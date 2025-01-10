<?php
/**
 * DATA_TYPE: cimt
 * 
 * Carotid Intima Ultrasound DICOM data
 */

namespace data_type;

require_once( __DIR__.'/base.class.php' );

class cimt extends base
{
  /**
   * Processes all cimt files
   */
  public static function process_files()
  {
    $base_dir = sprintf( '%s/%s', DATA_DIR, TEMPORARY_DIR );

    // Process all cimt recordings
    // We expect two cineloops (CINELOOP_X.dcm), two structured reports (SR_X.dcm) and 6 still images
    // (STILL_IMAGE_X.dcm), however, there may be more or less images for each type.
    // In order to determine which side the image belongs to we need to read the CIMT answer value in
    // Pine (where Cypress stored the file and side data in results[S].side and results[S].files[X].name
    output( sprintf( 'Processing cimt files in "%s"', $base_dir ) );

    try
    {
      $cenozo_db = \util::get_cenozo_db();
    }
    catch( \Exception $e )
    {
      fatal_error( 'Failed to open required connection to cenozo database.', 12 );
    }

    $link_list = [];
    $respondent_list = [];

    // This data only comes from the Pine Site interview
    $processed_uid_list = [];
    $file_count = 0;
    $process_file_list = [];
    $mismatch_list = [];
    foreach( glob( sprintf( '%s/nosite/Follow-up * Site/CIMT/*/*.dcm', $base_dir ) ) as $filename )
    {
      // move any unexpected filenames to the invalid directory
      $re = '#nosite/Follow-up ([0-9]) Site/CIMT/([^/]+)/(CINELOOP|SR|STILL_IMAGE)_([0-9]+)\.dcm$#';
      $matches = [];
      if( !preg_match( $re, $filename, $matches ) )
      {
        self::move_from_temporary_to_invalid( $filename, sprintf( 'Invalid filename: "%s"', $filename ) );
        continue;
      }

      $phase = $matches[1] + 1;
      $uid = $matches[2];
      $type = $matches[3];
      $number = $matches[4];
      if( !array_key_exists( $uid, $process_file_list ) ) $process_file_list[$uid] = [];

      // get file data info from Pine if we don't already have it for this participant
      if( !array_key_exists( $uid, $link_list ) )
      {
        $link_list[$uid] = NULL;

        $metadata = static::get_pine_metadata( $cenozo_db, $phase, $uid, 'CIMT' );
        if( is_null( $metadata ) ) continue;

        $obj = json_decode( $metadata['value'] );
        if(
          !is_object( $obj ) ||
          !property_exists( $obj, 'session' ) ||
          !property_exists( $obj->session, 'barcode' ) ||
          !property_exists( $obj->session, 'interviewer' ) ||
          !property_exists( $obj->session, 'end_time' ) ||
          !property_exists( $obj, 'results' ) ||
          !is_array( $obj->results )
        ) {
          output( sprintf( 'No result data in CIMT metadata from Pine for %s', $uid ) );
          continue;
        }

        // store the respondent data to load data into alder
        $metadata['interview_id'] = NULL; // used for caching
        $metadata['exam_list'] = []; // used for caching
        $respondent_list[$uid] = $metadata;

        // finish getting all of the raw data we need for the alder database
        $session = $obj->session;
        $respondent_list[$uid]['token'] = $session->barcode;
        $respondent_list[$uid]['interviewer'] = $session->interviewer;
        // convert to YYYY-MM-DD HH:mm:SS
        $respondent_list[$uid]['datetime'] = preg_replace( '/(.+)T(.+)\.[0-9]+Z/', '\1 \2', $session->end_time );

        foreach( $obj->results as $side_obj )
        {
          if(
            is_object( $side_obj ) &&
            property_exists( $side_obj, 'side' ) &&
            property_exists( $side_obj, 'files' ) &&
            is_array( $side_obj->files )
          ) {
            $list = [];

            // add the exam to the respondent list (for the alder database)
            $respondent_list[$uid]['exam_list'][$side_obj->side] = NULL;

            foreach( $side_obj->files as $file_obj_index => $file_obj )
            {
              if( is_object( $file_obj ) && property_exists( $file_obj, 'name' ) )
              {
                $matches = [];
                preg_match( '/(CINELOOP|SR|STILL_IMAGE)_([0-9]+)/', $file_obj->name, $matches );
                $json_type = $matches[1];
                $json_number = $matches[2];

                if( 'STILL_IMAGE' == $json_type )
                {
                  $list[$file_obj->name] = sprintf( 'still%d_%s.dcm', $json_number, $side_obj->side );
                }
                else
                {
                  $list[$file_obj->name] = sprintf(
                    '%s_%s.dcm',
                    strtolower( 'SR' == $json_type ? 'report' : $json_type ),
                    $side_obj->side
                  );
                }
              }
            }

            if( 0 < count( $list ) )
            {
              if( !is_array( $link_list[$uid] ) ) $link_list[$uid] = [];
              $link_list[$uid] = array_merge( $link_list[$uid], $list );
            }
          }
        }

        // renumber still image numbering to start from 1 for both right and left
        foreach( $link_list as $uid => $list )
        {
          $right_min = 999;
          $left_min = 999;
          foreach( $list as $name => $link )
          {
            $m = [];
            if( preg_match( '/still([0-9]+)_right/', $link, $m ) && $right_min > $m[1] ) $right_min = $m[1];
            if( preg_match( '/still([0-9]+)_left/', $link, $m ) && $left_min > $m[1] ) $left_min = $m[1];
          }

          if( $right_min > 1 )
          {
            foreach( $list as $name => $link )
            {
              $m = [];
              if( preg_match( '/still([0-9]+)_right/', $link, $m ) )
              {
                $link_list[$uid][$name] = sprintf( 'still%d_right.dcm', $m[1] - $right_min + 1 );
              }
            }
          }

          if( $left_min > 1 )
          {
            foreach( $list as $name => $link )
            {
              $m = [];
              if( preg_match( '/still([0-9]+)_left/', $link, $m ) )
              {
                $link_list[$uid][$name] = sprintf( 'still%d_left.dcm', $m[1] - $left_min + 1 );
              }
            }
          }
        }
      }
      else if( is_null( $link_list[$uid] ) ) continue; // db error, skip to the next participant

      $destination_directory = sprintf(
        '%s/%s/clsa/%s/carotid_intima/%s',
        DATA_DIR,
        RAW_DIR,
        $phase,
        $uid
      );
      $destination = sprintf( '%s/%s', $destination_directory, basename( $filename ) );

      $name = sprintf( '%s_%d', $type, $number );
      $link = array_key_exists( $name, $link_list[$uid] ) ?  $link_list[$uid][$name] : NULL;
      if( is_null( $link ) )
      {
        if( !array_key_exists( $uid, $mismatch_list ) ) $mismatch_list[$uid] = [];
        $mismatch_list[$uid][] = $name;
      }

      $process_file_list[$uid][] = [
        'dir' => $destination_directory,
        'source' => $filename,
        'dest' => $destination,
        'link' => $link,
      ];
    }

    foreach( $process_file_list as $uid => $pf_list )
    {
      if( array_key_exists( $uid, $mismatch_list ) )
      {
        // move the whole participant's directory to invalid
        self::move_from_temporary_to_invalid(
          dirname( $pf_list[0]['source'] ),
          sprintf(
            'File/data mismatch error: missing %s data',
            implode( ', ', $mismatch_list[$uid] )
          )
        );
      }
      else
      {
        // process each file, one at a time
        foreach( $pf_list as $pf_index => $pf )
        {
          if( self::process_file( $pf['dir'], $pf['source'], $pf['dest'], $pf['link'] ) )
          {
            $processed_uid_list[] = $uid;

            // generate supplementary data for SR reports
            if( !TEST_ONLY && preg_match( '#/SR_[0-9]+\.dcm#', $pf['dest'] ) )
              self::generate_supplementary( $pf['dest'], $pf['link'] );
            $file_count++;

            // register the interview, exam and still images in alder (if the alder db exists)
            if( !defined( 'ALDER_DB_DATABASE' ) ) continue;

            $image_side = preg_match( '/right/', $pf['link'] ) ? 'right' : 'left';
            $respondent = $respondent_list[$uid];

            // if the interview_id is false then there was an error trying to get or create it
            if( false === $respondent['interview_id'] ) continue;

            if( is_null( $respondent['interview_id'] ) )
            {
              $respondent['interview_id'] = static::assert_alder_interview(
                $cenozo_db,
                $respondent['participant_id'],
                $respondent['study_phase_id'],
                $respondent['site_id'],
                $respondent['token'],
                $respondent['start_datetime'],
                $respondent['end_datetime']
              );
              if( false === $respondent['interview_id'] )
              {
                output( sprintf( 'Unable to read or create interview data from Alder for %s', $uid ) );
                continue;
              }
            }

            foreach( $respondent['exam_list'] AS $side => $exam_id )
            {
              // only create the exam that the image belongs to
              if( !is_null( $exam_id ) || $side != $image_side ) continue;

              $respondent['exam_list'][$side] = static::assert_alder_exam(
                $cenozo_db,
                $respondent['interview_id'],
                'carotid_intima',
                $side,
                $respondent['interviewer'],
                $respondent['datetime']
              );

              if( false === $respondent['exam_list'][$side] )
              {
                output( sprintf( 'Unable to read or create exam data from Alder for %s', $uid ) );
                continue;
              }
            }

            // only insert still images
            if( !TEST_ONLY && preg_match( '/still/', $pf['link'] ) )
            {
              $image_id = static::assert_alder_image(
                $cenozo_db,
                $respondent['exam_list'][$image_side],
                $pf['link']
              );
              if( false === $image_id )
              {
                output( sprintf( 'Unable to read or create image "%s" from Alder for %s', $pf['link'], $uid ) );
              }
            }

            // store the respondent back into the list
            $respondent_list[$uid] = $respondent;
          }
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
      'Done, %d files from %d participants %stransferred',
      $file_count,
      count( array_unique( $processed_uid_list ) ),
      TEST_ONLY ? 'would be ' : ''
    ) );
  }

  /**
   * Anonymizes an cIMT DICOM file by removing identifying data
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
      '0008,1010' => 'VIVID_I',     // Station Name
      '0010,0020' => $identifier,   // Patient ID
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
   * This function will parse the cIMT values from the SR report file and add them to the report_summary.csv
   * file found in the root of the supplementary's carotid_intima folder.  It should only be used as the post
   * link function for us_report "SR" files.
   */
  public static function generate_supplementary( $filename, $link )
  {
    // determine the report summary filename
    $matches = [];
    if( !preg_match( sprintf( '#%s/([^/]+/[0-9]+)/carotid_intima#', RAW_DIR ), $filename, $matches ) ) return;
    $summary_directory = sprintf( '%s/%s/%s/carotid_intima', DATA_DIR, SUPPLEMENTARY_DIR, $matches[1] );
    $summary_filename = sprintf( '%s/report_summary.csv', $summary_directory, $matches[1] );

    // create the summary file if it doesn't already exist
    if( !file_exists( $summary_filename ) )
    {
      static::mkdir( $summary_directory );
      file_put_contents( $summary_filename, 'uid,side,rank,Average,Max,Min,SD,nMeas' );
    }

    // get the UID from the filename
    $matches = [];
    $uid = preg_match( '#/([A-Z0-9]+)/(SR|report)_#', $filename, $matches ) ? $matches[1] : NULL;

    // get the side from the link
    $matches = [];
    $side = preg_match( '#report_(.+)\.dcm#', $link, $matches ) ? $matches[1] : NULL;

    // only continue if we have a uid and side
    if( is_null( $uid ) || is_null( $side ) ) return;

    // remove this participant-side's entry in the report summary
    exec( sprintf(
      'sed -i "/^%s,%s,/d" %s',
      $uid,
      $side,
      $summary_filename
    ) );

    // get the report values from the dcm file
    $summary_data = [];
    $result_code = NULL;
    exec(
      sprintf( '%s/../../bin/get_us_pr_data %s', __DIR__, $filename ),
      $summary_data,
      $result_code
    );

    if( 0 == $result_code )
    {
      // add the uid and side to each line parsed from the report file
      foreach( $summary_data as $index => $line )
        $summary_data[$index] = sprintf( '%s,%s,%s', $uid, $side, $line );

      // now find where to insert the new data
      $insert_index = NULL;
      foreach( explode( "\n", file_get_contents( $summary_filename ) ) as $index => $line )
      {
        // skip the header
        if( 0 == $index ) continue;

        $line_uid = substr( $line, 0, 7 );
        if( $line_uid > $uid )
        {
          $insert_index = $index;
          break;
        }
      }

      if( is_null( $insert_index ) )
      {
        // add the data to the end of the file
        exec( sprintf(
          "echo '%s' >> %s",
          implode( "\n", $summary_data ), // join the data with newlines
          $summary_filename
        ) );
      }
      else
      {
        // insert data at the given index
        exec( sprintf(
          'sed -i "%d i %s" %s',
          $insert_index + 1,
          implode( '\n', $summary_data ), // join the data with \n (as a string)
          $summary_filename
        ) );
      }
    }
  }
}
