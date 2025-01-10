<?php
/**
 * DATA_TYPE: ecg
 * 
 * Electrocardiogram XML data
 */

namespace data_type;

require_once( __DIR__.'/base.class.php' );

class ecg extends base
{
  /**
   * Processes all ecg files
   */
  public static function process_files()
  {
    $base_dir = sprintf( '%s/%s', DATA_DIR, TEMPORARY_DIR );

    // Process all ecg recordings
    // One file named Ecg.xml for each participant, and possibly two others: Ecg.pdf and Ecg.ecg
    output( sprintf( 'Processing ecg files in "%s"', $base_dir ) );

    // This data only comes from the Pine Site interview
    $processed_uid_list = [];
    $file_count = 0;
    foreach( glob( sprintf( '%s/nosite/Follow-up * Site/ECG/*/*', $base_dir ) ) as $filename )
    {
      $re = '#nosite/Follow-up ([0-9]) Site/ECG/([^/]+)/Ecg\.(xml|pdf|ecg)$#';
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
      $extension = strtolower( $matches[3] );

      $destination_directory = sprintf(
        '%s/%s/clsa/%s/ecg/%s',
        DATA_DIR,
        RAW_DIR,
        $phase,
        $uid
      );
      $destination = sprintf(
        '%s/ecg.%s',
        $destination_directory,
        $extension
      );

      if( self::process_file( $destination_directory, $filename, $destination ) )
      {
        // generate supplementary data from the xml file (only needed for up to phase 4)
        if( 4 >= $phase && 'xml' == $extension && !TEST_ONLY ) self::generate_supplementary( $destination );

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

  /**
   * Anonymizes an ECG XML file by removing identifying data
   * @param string $filename The name of the file to anonymize
   * @param string $organization (not used)
   * @param string $identifier An optional value to set the identifier to (default is an empty string)
   */
  public static function anonymize( $filename, $organization = '', $identifier = '', $debug = false )
  {
    // remove the body of the Facility element
    $command = sprintf(
      'sed -i "s#<Facility>[^<]\+</Facility>#<Facility></Facility>#" %s',
      \util::format_filename( $filename )
    );
    $result_code = 0;
    $output = NULL;
    $debug ? printf( "%s\n", $command ) : exec( $command, $output, $result_code );
    if( 0 < $result_code ) printf( implode( "\n", $output ) );

    // remove the body of the Name element
    $command = sprintf(
      'sed -i "s#<Name>[^<]\+</Name>#<Name></Name>#" %s',
      \util::format_filename( $filename )
    );
    $result_code = 0;
    $output = NULL;
    $debug ? printf( "%s\n", $command ) : exec( $command, $output, $result_code );
    if( 0 < $result_code ) printf( implode( "\n", $output ) );

    // remove the body of the PID element
    $command = sprintf(
      'sed -i "s#<PID>[^<]\+</PID>#<PID>%s</PID>#" %s',
      $identifier,
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
   * This will generate jpeg versions of ECG XML files (plotting the values in the XML file).
   * It should only be used as the post download function for ecg XML files.
   */
  public static function generate_supplementary( $filename )
  {
    if( 0 < filesize( $filename ) )
    {
      $image_filename = preg_replace(
        [sprintf( '#/%s/#', RAW_DIR ), '#\.xml$#'],
        [sprintf( '/%s/', SUPPLEMENTARY_DIR ), '.jpeg'],
        $filename
      );
      $directory = dirname( $image_filename );
      if( !is_dir( $directory ) ) mkdir( $directory, 0755, true );

      exec( sprintf(
        '%s/../../bin/plot_ecg -r %s %s',
        __DIR__,
        $filename,
        $image_filename
      ) );
      return $image_filename;
    }

    return NULL;
  }
}
