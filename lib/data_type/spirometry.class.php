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
    $re1 = '#nosite/Follow-up ([0-9]) Site/SP_AUTO/([^/]+)/report\.pdf$#';
    $re2 = '#nosite/Follow-up ([0-9]) Site/SP_AUTO/([^/]+)/data\.xml$#';
    $processed_uid_list = [];
    $file_count = 0;
    foreach( glob( sprintf( '%s/nosite/Follow-up * Site/SP_AUTO/*/*', $base_dir ) ) as $filename )
    {
      $matches = [];
      if( preg_match( $re1, $filename, $matches ) )
      {
        $phase = $matches[1] + 1;
        $uid = $matches[2];

        $destination_directory = sprintf( '%s/%s/clsa/%s/spirometry/%s', DATA_DIR, RAW_DIR, $phase, $uid );
        $new_filename = 'report.pdf';
        $destination = sprintf( '%s/%s', $destination_directory, $new_filename );

        if( self::process_file( $destination_directory, $filename, $destination ) )
        {
          $processed_uid_list[] = $uid;
          $file_count++;

          static::write_data_to_alder(
            $cenozo_db,
            $phase,
            $uid,
            'SP_AUTO',
            'spirometry',
            'none',
            $new_filename
          );
        }
      }
      else if( preg_match( $re2, $filename, $matches ) )
      {
        $phase = $matches[1] + 1;
        $uid = $matches[2];

        $destination_directory = sprintf( '%s/%s/clsa/%s/spirometry/%s', DATA_DIR, RAW_DIR, $phase, $uid );
        $destination = sprintf( '%s/data.xml', $destination_directory );

        self::mkdir( $destination_directory );
        if( self::copy( $filename, $destination ) )
        {
          if( !TEST_ONLY ) static::generate_raw_data( $filename, $destination_directory );
          self::unlink( $filename );
          $file_count++;
        }
      }
      else
      {
        self::move_from_temporary_to_invalid(
          $filename,
          sprintf( 'Invalid filename: "%s"', $filename )
        );
        continue;
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
