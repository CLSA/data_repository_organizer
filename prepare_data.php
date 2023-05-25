<?php
require_once( 'settings.ini.php' );
require_once( 'src/common.php' );
require_once( 'src/arguments.class.php' );

/**
 * Prepares data for a particular study, phase and category using identifiers in the provided file
 * @param string $release_name The name of the release
 * @param string $data_path The relative path to the data to prepare (study/phase/category)
 * @param string $file_glob The relative path to the data to prepare (study/phase/category)
 * @param boolean $keep_files Whether to keep files which already exist
 * @param string $identifier_filename The name of the CSV file containing identifiers
 */
function prepare_data( $release_name, $data_path, $file_glob, $keep_files, $identifier_filename )
{
  // make sure the data path exists
  $data_path = sprintf( '%s/%s', DATA_DIR, $data_path );
  if( !file_exists( $data_path ) )
  {
    fatal_error( sprintf( 'No files exist for data path "%s"', $data_path ), 10 );
  }

  // make sure the identifier file exists and is valid
  $file = @fopen( $identifier_filename, 'r' );
  if( false === $file )
  {
    fatal_error( sprintf( 'Identifier filename "%s" is not a valid CSV file', $identifier_filename ), 11 );
  }

  $potential_participants = exec( sprintf(
    'grep -c \'\<[A-Z][0-9]\{6\}\>\' %s',
    format_filename( $identifier_filename )
  ) );
  output( sprintf(
    'Identifier CSV file contains %s potential participants for release, checking files...',
    $potential_participants
  ) );

  // loop over every line in the file
  $csv_count = 0;
  $participant_list = [];
  $file_count = 0;
  while( ($row = fgetcsv( $file )) !== false )
  {
    $uid = $row[0];

    // ignore all rows where the first column isn't a valid CLSA ID or doesn't have at least 2 columns
    if( 2 > count( $row ) || !preg_match( '/[A-Z][0-9]{6}/', $uid ) ) continue;

    $glob_files = glob( sprintf( '%s/%s/%s', $data_path, $uid, $file_glob ) );
    if( false === $glob_files )
    {
      VERBOSE && output( sprintf( 'CSV row %s: Error while globbing for %s', $uid, $file_glob ) );
    }
    else if( 0 == count( $glob_files ) )
    {
      VERBOSE && output( sprintf( 'CSV row %s: no files found', $uid ) );
    }
    else
    {
      // only include files with filesize > 0
      $files = [];
      foreach( $glob_files as $f ) if( 0 < filesize( $f ) ) $files[] = $f;

      if( 0 < count( $files ) )
      {
        $participant_list[] = [ 'uid' => $uid, 'identifier' => $row[1], 'files' => $files ];
        $count = count( $files );
        $file_count += $count;
        VERBOSE && output( sprintf( 'CSV row %s: %d files found', $uid, $count ) );
      }
      else
      {
        VERBOSE && output( sprintf( 'CSV row %s: no non-empty files found', $uid ) );
      }
    }

    $csv_count++;
  }
  fclose( $file );

  $participant_count = count( $participant_list );

  if( 0 == $participant_count )
  {
    output( 'No valid files found, aborting' );
  }
  else
  {
    output( sprintf(
      '%d files belonging to %d participants found',
      $file_count,
      $participant_count
    ) );

    // create the directory that prepared files will be copied to
    $release_path = sprintf( '%s/%s/%s', DATA_DIR, RELEASE_DIR, $release_name );
    if( !file_exists( $release_path ) )
    {
      if( !@mkdir( $release_path ) )
      {
        fatal_error( sprintf( 'Unable to create release directory "%s"', $release_path ), 12 );
      }
    }

    foreach( $participant_list as $index => $participant )
    {
      output( sprintf(
        'Processing %s (%d of %d)',
        $participant['uid'],
        $index+1,
        $participant_count
      ) );

      // create the destination directory
      $destination_path = sprintf( '%s/%s', $release_path, $participant['identifier'] );
      if( !file_exists( $destination_path ) ) mkdir( $destination_path );

      // copy all files to the destination directory
      foreach( $participant['files'] as $source_filename )
      {
        $destination_filename = sprintf(
          '%s/%s',
          $destination_path,
          // in case the filename includes the UID, rename it to the identifier
          str_replace( $participant['uid'], $participant['identifier'], basename( $source_filename ) )
        );
        $final_filename = preg_replace( '/.gz$/', '', $destination_filename );

        // don't overwrite existing files, if requested
        if( $keep_files && file_exists( $final_filename ) )
        {
          VERBOSE && output( sprintf( 'Skipping %s, file already exists', $final_filename ) );
          continue;
        }

        VERBOSE && output( sprintf( 'Copying %s to %s', $source_filename, $destination_filename ) );
        if( false == copy( $source_filename, $destination_filename ) ) continue;

        // decompress all files
        if( preg_match( '/.gz$/', $destination_filename ) )
        {
          VERBOSE && output( 'Uncompressing destination file' );
          exec( sprintf( 'gzip -d -f %s', format_filename( $destination_filename ) ) );
        }

        // set the identifier tag in dicom files only
        if( false == preg_match( '/.dcm$/', $final_filename ) ) continue;

        VERBOSE && output( sprintf(
          'Setting destination file\'s identifier to "%s"',
          $participant['identifier']
        ) );
        $result = set_dicom_identifier( $final_filename, $participant['identifier'] );
        if( 0 < $result )
        {
          // delete the invalid file and stop
          unlink( $final_filename );
          fatal_error(
            sprintf(
              'Failed to set identifier in "%s", error code "%s" returned by dcmodify (file removed from release)',
              $final_filename,
              $result
            ),
            13
          );
        }
      }
    }

    output( sprintf( 'Done, prepared files have been created in %s', $release_path ) );
  }
}


////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// build the command argument details, then parse the passed args
$arguments = new arguments;
$arguments->set_description(
  "Prepares a data release.\n".
  "This script will create a copy of participant data and apply a custom identifier.  ".
  "Note that only data belonging to participants provided in the identifier CSV file will be included.  ".
  "The CSV file should have a header row and contain two columns: the CLSA ID and identifier."
);
$arguments->add_option( 'v', 'verbose', 'Shows more details when running the script' );
$arguments->add_option( 'g', 'glob', 'Restrict to files matching a particular glob (eg: *.dcm)', true, '*' );
$arguments->add_option( 'k', 'keep_files', 'Keep files which have already been copied.' );
$arguments->add_input( 'RELEASE_NAME', 'The name of the release where files will be copied to' );
$arguments->add_input( 'DATA_PATH', 'The relative path to the data to prepare (eg: raw/clsa/1/dxa)' );
$arguments->add_input( 'IDENTIFIER_FILENAME', 'The CSV file containing the CLSA ID and identifier' );

$args = $arguments->parse_arguments( $argv );

define( 'VERBOSE', array_key_exists( 'verbose', $args['option_list'] ) );
$file_glob = $args['option_list']['glob'];
$keep_files = array_key_exists( 'keep_files', $args['option_list'] );
$release_name = $args['input_list']['RELEASE_NAME'];
$data_path = $args['input_list']['DATA_PATH'];
$identifier_filename = $args['input_list']['IDENTIFIER_FILENAME'];

prepare_data( $release_name, $data_path, $file_glob, $keep_files, $identifier_filename );
