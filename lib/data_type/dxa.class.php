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
    // TODO: describe expected file tree format
    output( sprintf( 'Processing dxa files in "%s"', $base_dir ) );

    // call self::generate_supplementary()
  }

  /**
   * Anonymizes an DXA DICOM file by removing identifying data
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
      '0010,0020' => $identifier, // Patient ID
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
   * This will generate jpeg versions of forearm, hip and wbody DICOM files for release to participants.
   * It will also create jpeg versions of forearm DICOM files for release to reserachers.  It should only
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
      $identifier = NULL;
      if( !preg_match( '/^[A-Z][0-9][0-9][0-9][0-9][0-9][0-9]$/', $uid ) )
        throw new Exception( sprintf( 'Invalid UID "%s" found while creating participant DXA image.', $uid ) );

      $result = $cenozo_db->query( sprintf(
        'SELECT participant_identifier.value '.
        'FROM participant_identifier '.
        'JOIN identifier ON participant_identifier.identifier_id = identifier.id '.
        'JOIN participant ON participant_identifier.participant_id = participant.id '.
        'WHERE identifier.name = "Results Correspondence" '.
        'AND participant.uid = "%s"',
        $uid
      ) );

      if( false === $result )
      {
        throw new Exception( sprintf(
          'Unable to get participant identifier for UID "%s" while creating participant DXA image.',
          $uid
        ) );
      }

      while( $row = $result->fetch_assoc() )
      {
        $identifier = $row['value'];
        break;
      }
      $result->free();

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
