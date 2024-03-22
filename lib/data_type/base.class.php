<?php
/**
 * The base class for all data types.
 */

namespace data_type;

require_once( __DIR__.'/../util.class.php' );

abstract class base
{
  /**
   * Reads the id_lookup file and returns an array containing Study ID => UID pairs
   */
  public static function get_study_uid_lookup( $identifier_name, $events = false, $consents = false )
  {
    $cenozo_db = \util::get_cenozo_db();

    $select_list = ['participant.uid', 'participant_identifier.value'];
    if( $events )
    {
      // create a temporary table for home and site events
      $cenozo_db->query( 'SELECT id INTO @home_id FROM event_type WHERE name = "completed (Follow-Up 3 Home)"' );
      $cenozo_db->query( 'DROP TABLE IF EXISTS home_event' );
      $cenozo_db->query(
        'CREATE TEMPORARY TABLE home_event '.
        'SELECT '.
          'participant.id AS participant_id, '.
          'DATE( CONVERT_TZ( event.datetime, "UTC", "Canada/Eastern" ) ) AS date '.
        'FROM participant '.
        'JOIN participant_last_event AS ple '.
          'ON participant.id = ple.participant_id '.
          'AND ple.event_type_id = @home_id '.
        'JOIN event AS event '.
          'ON ple.event_id = event.id'
      );
      $cenozo_db->query( 'ALTER TABLE home_event ADD PRIMARY KEY (participant_id)' );

      $cenozo_db->query( 'SELECT id INTO @site_id FROM event_type WHERE name = "completed (Follow-Up 3 Site)"' );
      $cenozo_db->query( 'DROP TABLE IF EXISTS site_event' );
      $cenozo_db->query(
        'CREATE TEMPORARY TABLE site_event '.
        'SELECT '.
          'participant.id AS participant_id, '.
          'DATE( CONVERT_TZ( event.datetime, "UTC", "Canada/Eastern" ) ) AS date '.
        'FROM participant '.
        'JOIN participant_last_event AS ple '.
          'ON participant.id = ple.participant_id '.
         'AND ple.event_type_id = @site_id '.
        'JOIN event AS event '.
          'ON ple.event_id = event.id'
      );
      $cenozo_db->query( 'ALTER TABLE site_event ADD PRIMARY KEY (participant_id)' );

      $select_list[] = 'home_event.date AS home_date';
      $select_list[] = 'site_event.date AS site_date';
    }
    if( $consents )
    {
      $select_list[] = 'IFNULL( sleep_consent.accept, false ) AS sleep_consent';
      $select_list[] = 'IFNULL( mobility_consent.accept, false ) AS mobility_consent';
    }

    $sql = sprintf(
      'SELECT %s '.
      'FROM participant '.
      'JOIN identifier '.
      'JOIN participant_identifier '.
        'ON identifier.id = participant_identifier.identifier_id '.
        'AND participant.id = participant_identifier.participant_id ',
      implode( ', ', $select_list )
    );

    if( $events )
    {
      $sql .=
        'LEFT JOIN home_event ON participant.id = home_event.participant_id '.
        'LEFT JOIN site_event ON participant.id = site_event.participant_id ';
    }

    if( $consents )
    {
      $sql .=
        'JOIN participant_last_consent AS sleep_plc ON participant.id = sleep_plc.participant_id '.
        'JOIN consent_type AS sleep_consent_type ON sleep_plc.consent_type_id = sleep_consent_type.id '.
          'AND sleep_consent_type.name = "F3 Sleep Trackers" '.
        'LEFT JOIN consent AS sleep_consent ON sleep_plc.consent_id = sleep_consent.id '.
        'JOIN participant_last_consent AS mobility_plc ON participant.id = mobility_plc.participant_id '.
        'JOIN consent_type AS mobility_consent_type ON mobility_plc.consent_type_id = mobility_consent_type.id '.
          'AND mobility_consent_type.name = "F3 Mobility Trackers" '.
        'LEFT JOIN consent AS mobility_consent ON mobility_plc.consent_id = mobility_consent.id ';
    }

    $sql .= sprintf( 'WHERE identifier.name = "%s"', $cenozo_db->real_escape_string( $identifier_name ) );

    $result = $cenozo_db->query( $sql );
    $cenozo_db->query( 'DROP TABLE IF EXISTS home_event' );
    $cenozo_db->query( 'DROP TABLE IF EXISTS site_event' );
    $cenozo_db->close();

    if( false === $result ) throw new Exception( 'Unable to get study UID lookup data.' );

    $data = [];
    while( $row = $result->fetch_assoc() ) $data[$row['value']] = ( $events || $consents ) ? $row : $row['uid'];
    $result->free();

    return $data;
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
   * @return boolean
   */
  public static function process_file( $directory, $source, $destination, $link = NULL )
  {
    $success = false;
    self::mkdir( $directory );
    if( self::copy( $source, $destination ) ) 
    {
      if( !is_null( $link ) ) self::symlink( $directory, $source, $link );
      self::unlink( $source );
      $success = true;
    }

    return $success;
  }
}
