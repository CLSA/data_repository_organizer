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
    $cwd = getcwd();
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

    $pine_db = str_replace( 'cenozo', 'pine', CENOZO_DB_DATABASE );
    $link_list = [];

    // This data only comes from the Pine Site interview
    $file_count = 0;
    $process_file_list = [];
    $mismatch_list = [];
    foreach( glob( sprintf( '%s/nosite/Follow-up * Site/CIMT/*/*.dcm', $base_dir ) ) as $filename )
    {
      // move any unexepcted filenames to the invalid directory
      $re = '#nosite/Follow-up ([0-9]) Site/CIMT/([^/]+)/(CINELOOP|SR|STILL_IMAGE)_([0-9]+)\.dcm$#';
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
      $type = $matches[3];
      $number = $matches[4];
      if( !array_key_exists( $uid, $process_file_list ) ) $process_file_list[$uid] = [];

      // get file data info from Pine if we don't already have it for this participant
      if( !array_key_exists( $uid, $link_list ) )
      {
        $link_list[$uid] = NULL;
        $result = $cenozo_db->query( sprintf(
          'SELECT value '.
          'FROM participant '.
          'JOIN %s.respondent ON participant.id = respondent.participant_id '.
          'JOIN %s.response ON respondent.id = response.respondent_id '.
          'JOIN %s.answer ON response.id = answer.response_id '.
          'JOIN %s.question on answer.question_id = question.id '.
          'WHERE participant.uid = "%s" '.
          'AND question.name = "CIMT"',
          $pine_db,
          $pine_db,
          $pine_db,
          $pine_db,
          $cenozo_db->real_escape_string( $uid )
        ) );
        if( false === $result )
        {
          output( sprintf( 'Unable to get Pine data needed to sort CIMT files for %s (1)', $uid ) );
          continue;
        }

        $row = $result->fetch_assoc();
        $result->free();
        if( is_null( $row ) )
        {
          output( sprintf( 'Unable to get Pine data needed to sort CIMT files for %s (2)', $uid ) );
          continue;
        }

        $obj = json_decode( $row['value'] );
        if( !is_object( $obj ) || !property_exists( $obj, 'results' ) || !is_array( $obj->results ) )
        {
          output( sprintf( 'Unable to get Pine data needed to sort CIMT files for %s (3)', $uid ) );
          continue;
        }

        foreach( $obj->results as $side_obj )
        {
          if(
            is_object( $side_obj ) &&
            property_exists( $side_obj, 'side' ) &&
            property_exists( $side_obj, 'files' ) &&
            is_array( $side_obj->files )
          ) {
            $list = [];
            $json_side = $side_obj->side;
            foreach( $side_obj->files as $file_obj_index => $file_obj )
            {
              if( is_object( $file_obj ) && property_exists( $file_obj, 'name' ) )
              {
                $json_name = $file_obj->name;
                $matches = [];
                preg_match( '/(CINELOOP|SR|STILL_IMAGE)_([0-9]+)/', $json_name, $matches );
                $json_type = $matches[1];
                $json_number = $matches[2];

                $list[$json_name] = 'STILL_IMAGE' == $json_type ?
                  sprintf( 'still%d_%s.dcm', $json_number, $json_side ) :
                  sprintf( '%s_%s.dcm', strtolower( 'SR' == $json_type ? 'report' : $json_type ), $json_side );
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
        'link' => $link
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
        foreach( $pf_list as $pf )
        {
          if( self::process_file( $pf['dir'], $pf['source'], $pf['dest'], $pf['link'] ) )
          {
            // generate supplementary data for SR reports
            if( preg_match( '#/SR_[0-9]+\.dcm#', $pf['dest'] ) )
              self::generate_supplementary( $pf['dest'], $pf['link'] );
            $file_count++;
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
      'Done, %d files %stransferred',
      $file_count,
      TEST_ONLY ? 'would be ' : ''
    ) );
  }

  /**
   * Anonymizes an cIMT DICOM file by removing identifying data
   * @param string $filename The name of the file to anonymize
   * @param string $identifier An optional value to set the identifier to (default is an empty string)
   */
  public static function anonymize( $filename, $identifier = '', $debug = false )
  {
    $tag_list = [
      '0008,1010' => '',          // Station Name
      '0008,0080' => 'CLSA',      // Instituion Name
      '0008,1040' => 'NCC',       // Instituion Department Name
      '0008,1070' => '',          // Operators Name
      '0010,0010' => '',          // Patient Name
      '0010,1000' => '',          // Other Patient IDs
      '0018,1000' => '',          // Device Serial Number
      '0008,1010' => 'VIVID_I',   // Station Name
      '0010,0020' => $identifier, // Patient ID
    ];

    $modify_list = [];
    foreach( $tag_list as $tag => $value )
    {
      $modify_list[] = sprintf( '-m "(%s)%s"', $tag, is_null( $value ) ? '' : sprintf( '=%s', $value ) );
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
    $summary_filename = sprintf(
      '%s/%s/%s/carotid_intima/report_summary.csv',
      DATA_DIR,
      SUPPLEMENTARY_DIR,
      $matches[1]
    );

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
