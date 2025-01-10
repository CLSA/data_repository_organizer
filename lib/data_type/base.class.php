<?php
/**
 * The base class for all data types.
 */

namespace data_type;

require_once( __DIR__.'/../util.class.php' );

abstract class base
{
  /**
   * Returns a participant's identifier by UID and identifier name
   * @param resource $cenozo_db
   * @param string $identifier_name The name of the identifier
   * @param string $uid The participant UID
   */
  public static function get_participant_identifier( $cenozo_db, $identifier_name, $uid )
  {
    $result = $cenozo_db->query( sprintf(
      'SELECT participant_identifier.value '.
      'FROM participant_identifier '.
      'JOIN identifier ON participant_identifier.identifier_id = identifier.id '.
      'JOIN participant ON participant_identifier.participant_id = participant.id '.
      'WHERE identifier.name = "%s" '.
      'AND participant.uid = "%s"',
      $cenozo_db->real_escape_string( $identifier_name ),
      $cenozo_db->real_escape_string( $uid )
    ) );

    if( false === $result )
    {
      throw new Exception( sprintf(
        'Unable to get "%s" participant identifier for UID "%s".',
        $identifier_name,
        $uid
      ) );
    }

    $identifier = NULL;
    while( $row = $result->fetch_assoc() )
    {
      $identifier = $row['value'];
      break;
    }
    $result->free();

    return $identifier;
  }

  /**
   * Reads the id_lookup file and returns an array containing Study ID => UID pairs
   */
  public static function get_study_uid_lookup( $identifier_name, $events = false )
  {
    $cenozo_db = \util::get_cenozo_db();

    $select_list = ['participant.uid', 'participant_identifier.value'];
    $extra_sql = '';
    if( $events )
    {
      // create temporary tables for home and site events for all completed event types (from F3 onward)
      $result = $cenozo_db->query(
        'SELECT study_phase.rank, event_type.id '.
        'FROM study '.
        'JOIN study_phase ON study.id = study_phase.study_id '.
        'JOIN event_type ON CONCAT( "completed (", study_phase.name, " Home)" ) = event_type.name '.
        'WHERE study.name = "clsa" '.
        'AND study_phase.rank >= 4'
      );

      if( false == $result )
      {
        throw new Exception( 'Unable to get event types while collecting study UID lookup data.' );
      }

      while( $row = $result->fetch_assoc() )
      {
        $temp_table_name = sprintf( 'home_event_%d', $row['rank'] );
        $cenozo_db->query( sprintf( 'DROP TABLE IF EXISTS %s', $temp_table_name ) );
        $cenozo_db->query( sprintf(
          'CREATE TEMPORARY TABLE %s '.
          'SELECT '.
            'participant.id AS participant_id, '.
            'DATE( CONVERT_TZ( event.datetime, "UTC", "Canada/Eastern" ) ) AS date '.
          'FROM participant '.
          'JOIN participant_last_event '.
            'ON participant.id = participant_last_event.participant_id '.
            'AND participant_last_event.event_type_id = %d '.
          'JOIN event ON participant_last_event.event_id = event.id',
          $temp_table_name,
          $row['id']
        ) );
        $cenozo_db->query( sprintf(  'ALTER TABLE %s ADD PRIMARY KEY (participant_id)', $temp_table_name ) );
        $select_list[] = sprintf( '%s.date AS home_date_%d', $temp_table_name, $row['rank'] );
        $extra_sql .= sprintf(
          'LEFT JOIN %s ON participant.id = %s.participant_id ',
          $temp_table_name,
          $temp_table_name
        );
      }

      $result = $cenozo_db->query(
        'SELECT study_phase.rank, event_type.id '.
        'FROM study '.
        'JOIN study_phase ON study.id = study_phase.study_id '.
        'JOIN event_type ON CONCAT( "completed (", study_phase.name, " Site)" ) = event_type.name '.
        'WHERE study.name = "clsa" '.
        'AND study_phase.rank >= 4'
      );

      if( false == $result )
      {
        throw new Exception( 'Unable to get event types while collecting study UID lookup data.' );
      }

      while( $row = $result->fetch_assoc() )
      {
        $temp_table_name = sprintf( 'site_event_%d', $row['rank'] );
        $cenozo_db->query( sprintf( 'DROP TABLE IF EXISTS %s', $temp_table_name ) );
        $cenozo_db->query( sprintf(
          'CREATE TEMPORARY TABLE %s '.
          'SELECT '.
            'participant.id AS participant_id, '.
            'DATE( CONVERT_TZ( event.datetime, "UTC", "Canada/Eastern" ) ) AS date '.
          'FROM participant '.
          'JOIN participant_last_event '.
            'ON participant.id = participant_last_event.participant_id '.
            'AND participant_last_event.event_type_id = %d '.
          'JOIN event ON participant_last_event.event_id = event.id',
          $temp_table_name,
          $row['id']
        ) );
        $cenozo_db->query( sprintf(  'ALTER TABLE %s ADD PRIMARY KEY (participant_id)', $temp_table_name ) );
        $select_list[] = sprintf( '%s.date AS site_date_%d', $temp_table_name, $row['rank'] );
        $extra_sql .= sprintf(
          'LEFT JOIN %s ON participant.id = %s.participant_id ',
          $temp_table_name,
          $temp_table_name
        );
      }
    }

    $sql = sprintf(
      'SELECT %s '.
      'FROM participant '.
      'JOIN identifier '.
      'JOIN participant_identifier '.
        'ON identifier.id = participant_identifier.identifier_id '.
        'AND participant.id = participant_identifier.participant_id %s '.
      'WHERE identifier.name = "%s"',
      implode( ', ', $select_list ),
      $extra_sql,
      $cenozo_db->real_escape_string( $identifier_name )
    );

    $result = $cenozo_db->query( $sql );
    $cenozo_db->query( 'DROP TABLE IF EXISTS home_event' );
    $cenozo_db->query( 'DROP TABLE IF EXISTS site_event' );
    $cenozo_db->close();

    if( false === $result ) throw new Exception( 'Unable to get study UID lookup data.' );

    $data = [];
    while( $row = $result->fetch_assoc() ) $data[$row['value']] = $events ? $row : $row['uid'];
    $result->free();

    return $data;
  }

  /**
   * Returns interview, exam and image based metadata from Pine
   * @param resource $cenozo_db
   * @param integer $phase The phase of the study
   * @param string $uid The participant UID
   * @param string $question The name of the question to get data from (CIMT, DXA1, DXA2, RET_L, RET_R, etc)
   * @return associative array [study_phase_id, participant_id, start_datetime, end_datetime, site_id, value]
   */
  public static function get_pine_metadata( $cenozo_db, $phase, $uid, $question )
  {
    $result = $cenozo_db->query( sprintf(
      'SELECT '.
        'study_phase.id AS study_phase_id, participant.id AS participant_id, '.
        'respondent.start_datetime, respondent.end_datetime, response.site_id, '.
        'site.name AS site, answer.value '.
      'FROM participant '.
      'JOIN %s.respondent ON participant.id = respondent.participant_id '.
      'JOIN %s.qnaire ON respondent.qnaire_id = qnaire.id '.
      'JOIN study_phase ON qnaire.name = CONCAT( study_phase.name, " Site" ) '.
      'JOIN study ON study_phase.study_id = study.id '.
      'JOIN %s.response ON respondent.id = response.respondent_id '.
      'JOIN %s.answer ON response.id = answer.response_id '.
      'JOIN %s.question on answer.question_id = question.id '.
      'LEFT JOIN site ON response.site_id = site.id '.
      'WHERE participant.uid = "%s" '.
      'AND study_phase.rank = %d '.
      'AND study.name = "clsa" '.
      'AND question.name = "%s" '.
      'ORDER BY study_phase.id, participant.id',
      PINE_DB_DATABASE,
      PINE_DB_DATABASE,
      PINE_DB_DATABASE,
      PINE_DB_DATABASE,
      PINE_DB_DATABASE,
      $cenozo_db->real_escape_string( $uid ),
      $phase,
      $cenozo_db->real_escape_string( $question )
    ) );

    $metadata = NULL;
    if( false === $result )
    {
      output( sprintf( 'Unable to get %s data from Pine for %s (1)', $question, $uid ) );
    }
    else
    {
      $metadata = $result->fetch_assoc();
      $result->free();
      if( is_null( $metadata ) )
      {
        output( sprintf( 'Unable to get %s data from Pine for %s (2)', $question, $uid ) );
      }
    }

    return $metadata;
  }

  /**
   * Reads interview, exam and image data from Pine and loads it into Alder
   * 
   * @param resource $cenozo_db
   * @param string $phase The phase of the study to write (1, 2, 3, etc)
   * @param string $uid The participant UID to write
   * @param string $question The Pine question name containing the interview metadata
   * @param string $type The type of image (hip, forearm, lateral, wbody, spine, retinal, etc)
   * @param string $side The anotomical side (left, right, none)
   * @param string $filename The name of the image file to write to Alder
   * @return boolean
   */
  public static function write_data_to_alder( $cenozo_db, $phase, $uid, $question, $type, $side, $filename )
  {
    $metadata = self::get_pine_metadata( $cenozo_db, $phase, $uid, $question );
    if( is_null( $metadata ) ) return false;

    $obj = json_decode( $metadata['value'] );
    if(
      !is_object( $obj ) ||
      !property_exists( $obj, 'session' ) ||
      !property_exists( $obj->session, 'barcode' ) ||
      !property_exists( $obj->session, 'interviewer' ) ||
      !property_exists( $obj->session, 'end_time' )
    ) {
      output( sprintf( 'No result data in %s metadata from Pine for %s', $question, $uid ) );
      return false;
    }

    $interview_id = self::assert_alder_interview(
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
      return false;
    }

    $exam_id = self::assert_alder_exam(
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
      return false;
    }

    if( false === self::assert_alder_image( $cenozo_db, $exam_id, $filename ) )
    {
      output( sprintf( 'Unable to read or create image "%s" from Alder for %s', $filename, $uid ) );
      return false;
    }

    return true;
  }

  /**
   * Inserts an interview record into alder (if it doesn't exist), returning the interview ID
   * @param resource $cenozo_db
   * @param integer $participant_id
   * @param integer $study_phase_id
   * @param integer $site_id
   * @param string $token
   * @param string $start_datetime
   * @param string $end_datetime
   * @return integer (NULL if record cannot be created, false if there is an error)
   */
  public static function assert_alder_interview(
    $cenozo_db, $participant_id, $study_phase_id, $site_id, $token, $start_datetime, $end_datetime
  ) {
    if( !defined( 'ALDER_DB_DATABASE' ) ) return NULL;

    // see if the interview already exists
    $result = $cenozo_db->query( sprintf(
      'SELECT id '.
      'FROM %s.interview '.
      'WHERE participant_id = "%s" '.
      'AND study_phase_id = %d',
      ALDER_DB_DATABASE,
      $participant_id,
      $study_phase_id
    ) );
    if( false === $result ) return false;

    $row = $result->fetch_assoc();
    $result->free();
    if( !is_null( $row ) )
    {
      // the interview already exists, take note of the id
      return $row['id'];
    }

    if( !TEST_ONLY )
    {
      // the interview doesn't exist, create it and return the new id
      $result = $cenozo_db->query( sprintf(
        'INSERT IGNORE INTO %s.interview SET '.
          'participant_id = %d, '.
          'study_phase_id = %d, '.
          'site_id = %d, '.
          'token = "%s", '.
          'start_datetime = "%s", '.
          'end_datetime = "%s"',
        ALDER_DB_DATABASE,
        $participant_id,
        $study_phase_id,
        $site_id,
        $cenozo_db->real_escape_string( $token ),
        $cenozo_db->real_escape_string( $start_datetime ),
        $cenozo_db->real_escape_string( $end_datetime )
      ) );
      return false === $result ? false : $cenozo_db->insert_id;
    }

    return NULL;
  }

  /**
   * Inserts an exam record into alder (if it doesn't exist), returning the exam ID
   * @param resource $cenozo_db
   * @param integer $interview_id
   * @param string $type
   * @param string $side
   * @param string $interviewer
   * @param string $datetime
   * @return integer (NULL if record cannot be created, false if there is an error)
   */
  public static function assert_alder_exam( $cenozo_db, $interview_id, $type, $side, $interviewer, $datetime )
  {
    if( !defined( 'ALDER_DB_DATABASE' ) ) return NULL;

    // see if the exam already exists
    $result = $cenozo_db->query( sprintf(
      'SELECT exam.id '.
      'FROM %s.exam '.
      'JOIN %s.scan_type ON exam.scan_type_id = scan_type.id '.
      'WHERE exam.interview_id = %d '.
      'AND scan_type.name = "%s" '.
      'AND scan_type.side = "%s"',
      ALDER_DB_DATABASE,
      ALDER_DB_DATABASE,
      $interview_id,
      $cenozo_db->real_escape_string( $type ),
      $cenozo_db->real_escape_string( $side )
    ) );
    if( false === $result ) return false;

    $row = $result->fetch_assoc();
    $result->free();
    if( !is_null( $row ) )
    {
      return $row['id'];
    }
    else if( !TEST_ONLY )
    {
      // the exam doesn't exist, create it and return the new id
      $result = $cenozo_db->query( sprintf(
        'INSERT IGNORE INTO %s.exam (interview_id, scan_type_id, interviewer, datetime) '.
        'SELECT %d, scan_type.id, "%s", "%s" '.
        'FROM %s.scan_type '.
        'WHERE scan_type.name = "%s" '.
        'AND scan_type.side = "%s"',
        ALDER_DB_DATABASE,
        $interview_id,
        $cenozo_db->real_escape_string( $interviewer ),
        $cenozo_db->real_escape_string( $datetime ),
        ALDER_DB_DATABASE,
        $cenozo_db->real_escape_string( $type ),
        $cenozo_db->real_escape_string( $side )
      ) );
      return false === $result ? false : $cenozo_db->insert_id;
    }

    return NULL;
  }

  /**
   * Inserts an image record into alder (if it doesn't exist), returning the image ID
   * @param integer $exam_id
   * @param string $filename
   * @return integer (NULL if record cannot be created, false if there is an error)
   */
  public static function assert_alder_image( $cenozo_db, $exam_id, $filename )
  {
    if( !defined( 'ALDER_DB_DATABASE' ) ) return NULL;

    // see if the image already exists
    $result = $cenozo_db->query( sprintf(
      'SELECT image.id '.
      'FROM %s.image '.
      'WHERE image.exam_id = %d '.
      'AND image.filename = "%s"',
      ALDER_DB_DATABASE,
      $exam_id,
      $cenozo_db->real_escape_string( $filename )
    ) );
    if( false === $result ) return false;

    $row = $result->fetch_assoc();
    $result->free();
    if( !is_null( $row ) )
    {
      return $row['id'];
    }
    else if( !TEST_ONLY )
    {
      // the image doesn't exist, create it and return the new id
      $result = $cenozo_db->query( sprintf(
        'INSERT IGNORE INTO %s.image SET exam_id = %d, filename = "%s"',
        ALDER_DB_DATABASE,
        $exam_id,
        $cenozo_db->real_escape_string( $filename )
      ) );
      return false === $result ? false : $cenozo_db->insert_id;
    }

    return NULL;
  }

  /**
   * Find and remove all empty directories
   */
  public static function remove_dir( $dir )
  {
    // first remove all empty sub directories
    foreach( glob( sprintf( '%s/*', $dir ), GLOB_ONLYDIR ) as $subdir ) self::remove_dir( $subdir );

    // now see if the directory is empty and remove it is it is
    if( 0 == count( glob( sprintf( '%s/*', $dir ) ) ) )
    {
      if( VERBOSE ) output( 'rmdir %s', $dir );
      if( !TEST_ONLY ) rmdir( $dir );
    }
  }

  /**
   * Used when files or a directory in the temporary folder is invalid
   * 
   * @param string $file_or_dir The file or directory to move
   * @param string $reason The string put into the error.txt file explaining why the data is invalid
   */
  public static function move_from_temporary_to_invalid( $file_or_dir, $reason = NULL )
  {
    if( VERBOSE && !is_null( $reason ) ) output( $reason );
    if( TEST_ONLY || KEEP_FILES ) return;

    // make sure the parent directory exists
    $rename_from = $file_or_dir;
    $rename_to = preg_replace(
      sprintf( '#/%s/#', TEMPORARY_DIR ),
      sprintf( '/%s/', INVALID_DIR ),
      $file_or_dir
    );
    $destination_dir = preg_replace( '#/[^/]+$#', '', $rename_to );

    // make sure the destination directory exists
    self::mkdir( $destination_dir );

    // if the rename_to file or dir already exists then delete it
    if( file_exists( $rename_to ) ) exec( sprintf( 'rm -rf %s', \util::format_filename( $rename_to ) ) );

    // move the file
    rename( $rename_from, $rename_to );
    // can't do this natively since there is no -R option
    exec( sprintf( 'chgrp -R sftpusers %s', \util::format_filename( $rename_to ) ) );

    // write the reason to a file in the temporary directory
    if( !is_null( $reason ) )
    {
      $reason_filename = sprintf( is_dir( $rename_to ) ? '%s/error.txt' : '%s.error.txt', $rename_to );
      file_put_contents( $reason_filename, sprintf( "%s\n", $reason ) );
      chgrp( $reason_filename, 'sftpusers' );
    }
  }

  /**
   * Copies a file while observing the VERBOSE and TEST_ONLY constants, returning success or not
   * 
   * Note that if the copy fails to copy then it will be moved to the invalid directory
   * @param string $source The file to copy
   * @param string $destination The destination to write the file to
   * @return boolean
   */
  public static function copy( $source, $destination )
  {
    if( VERBOSE ) output( sprintf( 'cp "%s" "%s"', $source, $destination ) );

    $success = TEST_ONLY ? true : copy( $source, $destination );
    if( !$success )
    {
      $reason = sprintf( 'Failed to copy "%s" to "%s"', $source, $destination );
      self::move_from_temporary_to_invalid( $source, $reason );
    }

    return $success;
  }

  /**
   * Deletes a file while observing the KEEP_FILES, VERBOSE and TEST_ONLY constants
   * 
   * @param string $filename
   */
  public static function unlink( $filename )
  {
    if( !KEEP_FILES )
    {
      if( VERBOSE ) output( sprintf( 'rm %s', $filename) );
      if( !TEST_ONLY ) unlink( $filename );
    }
  }

  /**
   * Creates a symbolic link while observing the VERBOSE and TEST_ONLY constants
   * 
   * Note that this will only work for links made in the same directory as the source file.
   * @param string $directory The directory to create the symlink
   * @param string $filename The source filename (with or without directory)
   * @param string $link The name of the target
   */
  public static function symlink( $directory, $filename, $link )
  {
    // get the current dir for when we're done
    $cwd = getcwd();

    // make sure the filename is relative
    $filename = basename( $filename );

    // move into the destination directory and create the symlink
    if( VERBOSE ) output( sprintf( 'cd %s', $directory ) );
    if( !TEST_ONLY ) chdir( $directory );

    if( file_exists( $link ) )
    {
      if( VERBOSE ) output( sprintf( 'rm %s', $link ) );
      if( !TEST_ONLY ) unlink( $link );
    }
    if( VERBOSE ) output( sprintf( 'link -s %s %s', $filename, $link ) );
    if( !TEST_ONLY ) symlink( $filename, $link );

    if( VERBOSE ) output( sprintf( 'cd %s', $cwd ) );
    if( !TEST_ONLY ) chdir( $cwd );
  }

  /**
   * Recursively creates a directory while observing the VERBOSE and TEST_ONLY constants
   * 
   * If the directory already exists then this does nothing
   * @param string $dir The directory to create
   */
  public static function mkdir( $dir )
  {
    // make sure the directory exists (recursively)
    if( !is_dir( $dir ) )
    {
      if( VERBOSE ) output( sprintf( 'mkdir -m 0755 %s', $dir ) );
      if( !TEST_ONLY ) mkdir( $dir, 0755, true );
    }
  }

  /**
   * Processes a temporary for permanent storage, returning whether it is successful
   * 
   * This function will create the target directory, copy the file to it and if successful
   * it will also remove the source file and create the symlink (if provided).
   * @param string $directory The destination directory
   * @param string $source The file to process
   * @param string $destination The destination filename
   * @param string $link A link to create (none if left empty)
   * @param boolean $remove_source Whether to delete the source file after it has been processed
   * @return boolean
   */
  public static function process_file( $directory, $source, $destination, $link = NULL, $remove_source = true )
  {
    $success = false;
    self::mkdir( $directory );
    if( self::copy( $source, $destination ) )
    {
      if( !is_null( $link ) ) self::symlink( $directory, $source, $link );
      if( $remove_source ) self::unlink( $source );
      $success = true;
    }

    return $success;
  }

  /**
   * Anonymizes the file by removing identifying data (must be implemented by extending class
   * @param string $filename The name of the file to anonymize
   * @param string $organization An option value to set the organization to (default is an empty string)
   * @param string $identifier An optional value to set the identifier to (default is an empty string)
   * @return result code (0 if normal)
   */
  public static function anonymize( $filename, $organization = '', $identifier = '', $debug = false )
  {
    return 0;
  }
}
