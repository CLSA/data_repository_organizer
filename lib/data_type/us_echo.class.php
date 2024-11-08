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
    // We expect the following files:
    //   One SRc file, multiple US files and multiple USm files (all numbered)
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
      $re = '#nosite/Follow-up ([0-9]) Site/ECHO/([^/]+)/(SRc|USm|US|SC).*\.dcm$#';
      $matches = [];
      if( !preg_match( $re, $filename, $matches ) )
      {
        self::move_from_temporary_to_invalid( $filename, sprintf( 'Invalid filename: "%s"', $filename ) );
        continue;
      }

      $phase = $matches[1] + 1;
      $uid = $matches[2];
      $type = $matches[3]; // not used
      if( !array_key_exists( $uid, $process_file_list ) ) $process_file_list[$uid] = [];

      $destination_directory = sprintf(
        '%s/%s/clsa/%s/us_echo/%s',
        DATA_DIR,
        RAW_DIR,
        $phase,
        $uid
      );

      $destination = sprintf( '%s/%s', $destination_directory, basename( $filename ) );

      $process_file_list[$uid][] = [
        'dir' => $destination_directory,
        'source' => $filename,
        'dest' => $destination
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
        // make a temporary copy of the file, anonymize it and send it to the remote PACS server
        $anon_filename = sprintf(
          '%s/temp_%s_%s.dcm',
          DATA_DIR,
          bin2hex( openssl_random_pseudo_bytes( 2 ) ),
          bin2hex( openssl_random_pseudo_bytes( 2 ) )
        );
        self::copy( $pf['source'], $anon_filename );

        // get the site name from the pine metadata and include it in anonymization
        $metadata = static::get_pine_metadata( $cenozo_db, $phase, $uid, 'ECHO' );
        $organization = sprintf(
          'CLSA (%s)',
          !is_null( $metadata ) && array_key_exists( 'site', $metadata ) ? $metadata['site'] : 'unknown'
        );

        self::anonymize( $anon_filename, $organization, $identifier_list[$uid], TEST_ONLY );
        $result_code = self::pacs_transfer( $anon_filename, TEST_ONLY );
        if( 0 != $result_code )
        {
          output( sprintf(
            'Unable to transfer anonymized ECHO file "%s" to remote PACS server (code %d).',
            $anon_filename,
            $result_code
          ) );
        }
        else
        {
          self::unlink( $anon_filename );
        }

        // now process the file
        if( self::process_file( $pf['dir'], $pf['source'], $pf['dest'] ) ) $file_count++;
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
   * @param string $organization An optional value to set the organization to (default is an empty string)
   * @param string $identifier An optional value to set the identifier to (default is an empty string)
   */
  public static function anonymize( $filename, $organization = '', $identifier = '', $debug = false )
  {
    $tag_list = [
      '0008,1010' => '',            // Station Name
      '0008,0050' => $organization, // Accession Number (because Institution Name can't be seen by end users)
      '0010,0010' => '',            // Patient Name
      '0010,1000' => '',            // Other Patient IDs
      '0018,1000' => '',            // Device Serial Number
      '0008,1010' => 'Vivid iq',    // Station Name
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
   * Anonymizes an ECHO DICOM file by removing identifying data
   * @param string $filename The name of the file to send
   * @param string $identifier An optional value to set the identifier to (default is an empty string)
   */
  public static function pacs_transfer( $filename, $debug = false )
  {
    $command = sprintf(
      'dcmsend -aet %s -aec %s %s %d %s',
      PACS_LOCAL_AE_TITLE,
      PACS_REMOTE_AE_TITLE,
      PACS_SERVER,
      PACS_PORT,
      $filename
    );

    $result_code = 0;
    $output = NULL;
    $debug ? printf( "%s\n", $command ) : exec( $command, $output, $result_code );

    if( 0 < $result_code ) printf( implode( "\n", $output ) );
    return $result_code;
  }
}
