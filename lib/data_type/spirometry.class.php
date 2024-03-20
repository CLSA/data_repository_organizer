<?php
/**
 * DATA_TYPE: spirometry
 * 
 * Bone density DXA DICOM files
 */

namespace data_type;

require_once( __DIR__.'/base.class.php' );

class spirometry extends base
{
  /**
   * Processes all spirometry files
   */
  public static function process_files()
  {
    $base_dir = sprintf( '%s/%s', DATA_DIR, TEMPORARY_DIR );

    // Process all spirometry data
    // There are two files, report.pdf and data.xml  Raw data must be extracted from the data.xml file.
    output( sprintf( 'Processing spirometry files in "%s"', $base_dir ) );

    // This data only comes from the Pine Site interview
    $file_count = 0;
    foreach( glob( sprintf( '%s/nosite/Follow-up * Site/SP_AUTO/*/*', $base_dir ) ) as $filename )
    {
      $matches = [];
      if( preg_match( '#nosite/Follow-up ([0-9]) Site/SP_AUTO/([^/]+)/report\.pdf$#', $filename, $matches ) )
      {
        $destination_directory = sprintf(
          '%s/%s/clsa/%s/spirometry/%s',
          DATA_DIR,
          RAW_DIR,
          $matches[1] + 1, // phase
          $matches[2] // UID
        );

        // make sure the directory exists (recursively)
        if( !is_dir( $destination_directory ) )
        {
          if( VERBOSE ) output( sprintf( 'mkdir -m 0755 %s', $destination_directory ) );
          if( !TEST_ONLY ) mkdir( $destination_directory, 0755, true );
        }

        $destination = sprintf( '%s/report.pdf', $destination_directory );
        if( TEST_ONLY ? true : copy( $filename, $destination ) )
        {
          if( VERBOSE ) output( sprintf( 'cp "%s" "%s"', $filename, $destination ) );
          if( !TEST_ONLY && !KEEP_FILES ) unlink( $filename );
          $file_count++;
        }
        else
        {
          $reason = sprintf(
            'Failed to copy "%s" to "%s"',
            $filename,
            $destination
          );
          self::move_from_temporary_to_invalid( $filename, $reason );
        }
      }

      $matches = [];
      if( preg_match( '#nosite/Follow-up ([0-9]) Site/SP_AUTO/([^/]+)/data\.xml$#', $filename, $matches ) )
      {
        $destination_directory = sprintf(
          '%s/%s/clsa/%s/spirometry/%s',
          DATA_DIR,
          RAW_DIR,
          $matches[1] + 1, // phase
          $matches[2] // UID
        );

        // make sure the directory exists (recursively)
        if( !is_dir( $destination_directory ) )
        {
          if( VERBOSE ) output( sprintf( 'mkdir -m 0755 %s', $destination_directory ) );
          if( !TEST_ONLY ) mkdir( $destination_directory, 0755, true );
        }

        $destination = sprintf( '%s/data.xml', $destination_directory );
        if( TEST_ONLY ? true : copy( $filename, $destination ) )
        {
          if( VERBOSE )
          {
            output( sprintf( 'cp "%s" "%s"', $filename, $destination ) );
            output( 'generating raw data files from XML data' );
          }
          if( !TEST_ONLY )
          {
            // create raw data files
            static::generate_raw_data( $filename, $destination_directory );
            if( !KEEP_FILES ) unlink( $filename );
          }
          $file_count++;
        }
        else
        {
          $reason = sprintf(
            'Failed to copy "%s" to "%s"',
            $filename,
            $destination
          );
          self::move_from_temporary_to_invalid( $filename, $reason );
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

  /**
   * Generates the raw flow and volume files from a spirometry XML file
   * 
   * @param string $xml_filename The XML file containing flow and volume data
   * @param string $destination_directory Where to write the flow and volume files
   */
  public static function generate_raw_data( $xml_filename, $destination_directory )
  {
    $xml = simplexml_load_file( $xml_filename );

    // will return an array, or empty if there are no trials (or XML is missing data)
    $trials = $xml->xpath( '/ndd/Patients/Patient/Intervals/Interval/Tests/Test/Trials/Trial' );

    foreach( $trials as $trial )
    {
      $number = strval( $trial->Number );
      $flow_data = strval( $trial->ChannelFlow->SamplingValues );
      $volume_data = strval( $trial->ChannelVolume->SamplingValues );
      $result = file_put_contents(
        sprintf( '%s/spirometry_flow_%d.txt', $destination_directory, $number ),
        $flow_data
      );
      file_put_contents(
        sprintf( '%s/spirometry_volume_%d.txt', $destination_directory, $number ),
        $volume_data
      );
    }
  }
}
