<?php
/**
 * DATA_TYPE: audio
 * 
 * All audio files recorded during interviews over VoIP and in person.
 */

namespace data_type;

require_once( __DIR__.'/base.class.php' );

class audio extends base
{
  /**
   * Processes all audio files
   */
  public static function process_files()
  {
    $base_dir = sprintf( '%s/%s', DATA_DIR, TEMPORARY_DIR );

    // Process all audio recordings
    // There is a directory for each cohort, and a sub-directory for each phase named "f1", "f2", etc.
    // Each of these directories contain a directory based on the participant's CLSA ID, and in it multiple files.
    // Each file has the format: "<cohort>/<phase>/<uid>/<various>.wav"
    output( sprintf( 'Processing audio files in "%s"', $base_dir ) );

    // start by deleting all non-live instances
    foreach( glob( sprintf( '%s/*/audio/tracking/*', $base_dir ) ) as $dirname )
    {
      if( 0 === preg_match( '/-live$/', $dirname ) )
      {
        if( TEST_ONLY || KEEP_FILES )
        {
          if( VERBOSE ) output( sprintf(
            'Non-production audio directory "%s" would be removed.',
            $dirname
          ) );
        }
        else
        {
          if( VERBOSE ) output( sprintf(
            'Removing non-production audio directory "%s"',
            $dirname
          ) );
          exec( sprintf( 'rm -rf %s', \util::format_filename( $dirname ) ) );
        }
      }
    }

    // now go through files that are labelled by UID (valid audio recordings)
    $processed_uid_list = [];
    $file_count = 0;

    $filename_list = array_merge(
      // pine-based comprehensive recordings
      glob( sprintf( '%s/nosite/*/*/*/audio.wav', $base_dir ) ),
      // CATI tracking and onyx-based comprehensive recordings
      glob( sprintf( '%s/nosite/audio/*/*/*/*.wav', $base_dir ) )
    );

    foreach( $filename_list as $filename )
    {
      $uid = NULL;
      $phase = NULL;
      $study = NULL;
      $destination_filename = NULL;

      $matches = [];
      // pine-based comprehensive recordings
      if( preg_match( '#nosite/Follow-up ([0-9]) (Home|Site)/([^/]+)/([^/]+)/audio\.wav$#', $filename, $matches ) )
      {
        $phase = $matches[1] + 1;
        $variable = $matches[3];
        $uid = $matches[4];
        $study = 'clsa';

        $destination_filename = NULL;
        if( 'FAS_FREC' == $variable ) $destination_filename = 'f_word_fluency';
        else if( 'FAS_AREC' == $variable ) $destination_filename = 'a_word_fluency';
        else if( 'FAS_SREC' == $variable ) $destination_filename = 's_word_fluency';
        else if( 'STP_DOTREC' == $variable ) $destination_filename = 'stroop_dot';
        else if( 'STP_WORDREC' == $variable ) $destination_filename = 'stroop_word';
        else if( 'STP_COLREC' == $variable ) $destination_filename = 'stroop_colour';
        else if( 'COG_ALPTME_REC2' == $variable ) $destination_filename = 'alphabet';
        else if( in_array( $variable, ['COG_ALTTME_REC', 'COG_ALTTME_REC2'] ) )
          $destination_filename = 'mental_alternation';
        else if( 'COG_ANMLLLIST_REC' == $variable ) $destination_filename = 'animal_fluency';
        else if( 'COG_CNTTMEREC' == $variable ) $destination_filename = 'counting';
        else if( 'COG_WRDLST2_REC' == $variable ) $destination_filename = 'delayed_word_list';
        else if( 'COG_WRDLSTREC' == $variable ) $destination_filename = 'immediate_word_list';
      }
      // CATI tracking and onyx-based comprehensive recordings
      else if( preg_match( '#nosite/audio/([^/]+)/([^/]+)/([^/]+)/([^/]+)\.wav$#', $filename, $matches ) )
      {
        $cohort = $matches[1];
        $phase_name = $matches[2];
        $uid = $matches[3];
        $name = $matches[4];

        $phase = NULL;
        $study = NULL;
        $destination_filename = NULL;
        if( 'comprehensive' == $cohort )
        {
          // comprehensive phase names are f1, f2, etc...
          $study = 'clsa';
          $phase = intval( substr( $phase_name, 1, 1 ) ) + 1;
          $variable = preg_replace( '/_(COF[1-9]|DCS)$/', '', $name );

          if( 'FAS_FREC' == $variable ) $destination_filename = 'f_word_fluency';
          else if( 'FAS_AREC' == $variable ) $destination_filename = 'a_word_fluency';
          else if( 'FAS_SREC' == $variable ) $destination_filename = 's_word_fluency';
          else if( 'STP_DOTREC' == $variable ) $destination_filename = 'stroop_dot';
          else if( 'STP_WORDREC' == $variable ) $destination_filename = 'stroop_word';
          else if( 'STP_COLREC' == $variable ) $destination_filename = 'stroop_colour';
          else if( 'COG_ALPTME_REC2' == $variable ) $destination_filename = 'alphabet';
          else if( 'COG_ALTTME_REC' == $variable ) $destination_filename = 'mental_alternation';
          else if( 'COG_ANMLLLIST_REC' == $variable ) $destination_filename = 'animal_fluency';
          else if( 'COG_CNTTMEREC' == $variable ) $destination_filename = 'counting';
          else if( 'COG_WRDLST2_REC' == $variable ) $destination_filename = 'delayed_word_list';
          else if( 'COG_WRDLSTREC' == $variable ) $destination_filename = 'immediate_word_list';
        }
        else if( 'tracking' == $cohort )
        {
          // tracking phase names refer to the application, study phase and instance:
          // sabretooth_f1-live, sabretooth_f2-live, sabretooth_c1-live, etc
          if( 0 === preg_match( '/sabretooth_(bl|cb|[cf][1-9])-live/', $phase_name, $matches ) )
          {
            self::move_from_temporary_to_invalid(
              $filename,
              sprintf( 'Ignoring invalid audio file: "%s"', $filename )
            );
            continue;
          }

          $code = $matches[1];

          if( 'bl' == $code || 'f' == substr( $code, 0, 1 ) )
          {
            // bl is baseline (phase 1), otherwise f1, f2, etc...
            $phase = 'bl' == $code ? 1 : intval( substr( $code, 1, 1 ) ) + 1;
            $study = 'clsa';
          }
          else if( 'cb' == $code || 'c' == substr( $code, 0, 1 ) )
          {
            // cb is baseline (phase 1), otherwise c1, c2, etc...
            $phase = 'cb' == $code ? 1 : intval( substr( $code, 1, 1 ) ) + 1;
            $study = 'covid_brain';
          }
          else
          {
            self::move_from_temporary_to_invalid(
              $filename,
              sprintf( 'Ignoring invalid audio file "%s"', $filename )
            );
            continue;
          }

          if( !is_null( $phase ) && !is_null( $study ) )
          {
            if( preg_match( '#([^-]+)-(in|out)#', $name, $matches ) )
            {
              $variable = strtolower( $matches[1] );
              $operator = 'in' == $matches[2];
              if( preg_match( '#^[0-9][0-9]$#', $variable ) ) $destination_filename = $variable;
              else if( 'alphabet' == $variable ) $destination_filename = 'alphabet';
              else if( 'mat alternation' == $variable ) $destination_filename = 'mental_alternation';
              else if( 'animal list' == $variable ) $destination_filename = 'animal_fluency';
              else if( 'counting to 20' == $variable ) $destination_filename = 'counting';
              else if( 'rey i' == $variable ) $destination_filename = 'immediate_word_list';
              else if( 'rey ii' == $variable ) $destination_filename = 'delayed_word_list';
              else fatal_error( sprintf( 'Invalid filename "%s"', $filename ), 31 );

              // add the operator tag if the audio is of the operator (in)
              if( !is_null( $destination_filename ) && $operator ) $destination_filename .= '-operator';
            }
          }
        }
      }
      else
      {
        self::move_from_temporary_to_invalid(
          $filename,
          sprintf( 'Ignoring invalid audio file: "%s"', $filename )
        );
        continue;
      }

      if( !is_null( $study ) && !is_null( $phase ) && !is_null( $destination_filename ) )
      {
        $destination_directory = sprintf(
          '%s/%s/%s/%s/audio/%s',
          DATA_DIR,
          RAW_DIR,
          $study,
          $phase,
          $uid
        );
        $destination = sprintf( '%s/%s.wav', $destination_directory, $destination_filename );

        if( self::process_file( $destination_directory, $filename, $destination ) )
        {
          $processed_uid_list[] = $uid;
          $file_count++;
        }
      }
      else
      {
        self::move_from_temporary_to_invalid(
          $filename,
          sprintf( 'Unable to process file "%s"', $filename )
        );
        continue;
      }
    }

    // now remove all empty directories
    foreach( glob( sprintf( '%s/*/audio/*/*', $base_dir ) ) as $dirname )
    {
      if( is_dir( $dirname ) ) self::remove_dir( $dirname );
    }

    // any remaining files are to be moved to the invalid directory for data cleaning
    foreach( glob( sprintf( '%s/*/audio/*/*/*/*', $base_dir ) ) as $dirname )
    {
      self::move_from_temporary_to_invalid(
        $dirname,
        sprintf( 'Unable to sort directory, "%s"', $dirname)
      );
    }

    output( sprintf(
      'Done, %d files from %d participants %stransferred',
      $file_count,
      count( array_unique( $processed_uid_list ) ),
      TEST_ONLY ? 'would be ' : ''
    ) );
  }
}
