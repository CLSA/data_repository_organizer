<?php
/**
 * DATA_TYPE: us_echo
 * 
 * Carotid Intima Ultrasound DICOM data
 */

namespace data_type;

require_once( __DIR__.'/base.class.php' );

class us_echo extends base
{
  /**
   * Processes all us_echo files
   */
  public static function process_files()
  {
    $base_dir = sprintf( '%s/%s', DATA_DIR, TEMPORARY_DIR );

    // Process all us_echo recordings
    // We expect the following files: TODO
    output( sprintf( 'Processing us_echo files in "%s"', $base_dir ) );

    try
    {
      $cenozo_db = \util::get_cenozo_db();
    }
    catch( \Exception $e )
    {
      fatal_error( 'Failed to open required connection to cenozo database.', 12 );
    }

    // This data only comes from the Pine Site interview
    $identifier_list = [];
    $file_count = 0;
    $process_file_list = [];
    foreach( glob( sprintf( '%s/nosite/Follow-up * Site/ECHO/*/*.dcm', $base_dir ) ) as $filename )
    {
      // move any unexpected filenames to the invalid directory
      $re = '#nosite/Follow-up ([0-9]) Site/ECHO/([^/]+)/(Src|US|USm)_(.+)\.dcm$#';
      $matches = [];
      if( !preg_match( $re, $filename, $matches ) )
      {
        self::move_from_temporary_to_invalid( $filename, sprintf( 'Invalid filename: "%s"', $filename ) );
        continue;
      }

      $phase = $matches[1] + 1;
      $uid = $matches[2];
      $type = $matches[3];
      $dcm_code = $matches[4];
      if( !array_key_exists( $uid, $process_file_list ) ) $process_file_list[$uid] = [];

      $destination_directory = sprintf(
        '%s/%s/clsa/%s/us_echo/%s',
        DATA_DIR,
        RAW_DIR,
        $phase,
        $uid
      );

      // TODO: name files based on expected output
      $destination = sprintf( '%s/%s', $destination_directory, basename( $filename ) );

      $name = sprintf( '%s_%d', $type, $number );
      $link = '???'; // TODO: determine link names for ECHO files

      $process_file_list[$uid][] = [
        'dir' => $destination_directory,
        'source' => $filename,
        'dest' => $destination,
        'link' => $link,
      ];

      // get the identifier for sending files to UBC
      if( !array_key_exists( $uid, $identifier_list ) )
      {
        $identifier_list[$uid] = self::get_participant_identifier( $cenozo_db, 'EchoImageIDs_UBC', $uid );
      }
    }

    foreach( $process_file_list as $uid => $pf_list )
    {
      // process each file, one at a time
      foreach( $pf_list as $pf_index => $pf )
      {
        // process file without deleting the source
        if( self::process_file( $pf['dir'], $pf['source'], $pf['dest'], $pf['link'], false ) )
        {
          // now anonymize and send the source file to UBC
          self::anonymize( $pf['source'], $identifier_list[$uid], TEST_ONLY );
          self::pacs_transfer( $pf['source'], TEST_ONLY );
          self::unlink( $pf['source'] );
          $file_count++;
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
   * Anonymizes an ECHO DICOM file by removing identifying data
   * @param string $filename The name of the file to anonymize
   * @param string $identifier An optional value to set the identifier to (default is an empty string)
   */
  public static function anonymize( $filename, $identifier = '', $debug = false )
  {
    // TODO: determine if these are the correct tags
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
   * Anonymizes an ECHO DICOM file by removing identifying data
   * @param string $filename The name of the file to anonymize
   * @param string $identifier An optional value to set the identifier to (default is an empty string)
   */
  public static function pacs_transfer( $filename, $debug = false )
  {
    // TODO: implement
  }
}
