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
    // TODO: describe expected file tree format
    output( sprintf( 'Processing cimt files in "%s"', $base_dir ) );

    // call self::generate_supplementary()
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
    $uid = preg_match( '#/([A-Z0-9]+)/report_#', $filename, $matches ) ? $matches[1] : NULL;

    // get the side from the link
    $matches = [];
    $side = preg_match( '#report_(.+)\.dcm.gz#', $link, $matches ) ? $matches[1] : NULL;

    // only continue if we have a uid and side
    if( is_null( $uid ) || is_null( $side ) ) return;

    // remove this participant-side's entry in the report summary
    exec( sprintf(
      'sed -i "/^%s,%s,/d" %s',
      $uid,
      $side,
      $summary_filename
    ) );

    // decompress the report file
    $decompressed_filename = \util::decompress_file( $filename );

    // only continue if we successfully got a decompressed file
    if( is_null( $decompressed_filename ) ) return;

    // get the report values from the dcm file
    $summary_data = [];
    $result_code = NULL;
    exec(
      sprintf( '%s/bin/get_us_pr_data %s', __DIR__, $decompressed_filename ),
      $summary_data,
      $result_code
    );

    // delete the temporary decompressed file now that we're done with it
    unlink( $decompressed_filename );

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
