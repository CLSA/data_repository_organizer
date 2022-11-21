<?php
require_once 'common.php';

$base_dir = sprintf( '%s/%s', DATA_DIR, TEMPORARY_DIR );

// Process all Actigraph files
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
      exec( sprintf( 'rm -rf %s', $dirname ) );
    }
  }
}

// start by going through files that are labelled by UID (valid audio recordings)
$file_count = 0;
foreach( glob( sprintf( '%s/*/audio/*/*/*/*.wav', $base_dir ) ) as $filename )
{
  $matches = [];
  if( false === preg_match( '#/([^/]+)/([^/]+)/([^/]+)/([^/]+).wav$#', $filename, $matches ) )
  {
    if( VERBOSE ) output( sprintf(
      'Ignoring invalid audio file: "%s"',
      $filename
    ) );
    if( !TEST_ONLY && !KEEP_FILES ) move_from_temporary_to_invalid( $filename );
    continue;
  }
  
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

    if( 'FAS_FREC' == $variable ) $destination_filename = 'f_word_fluency.wav';
    else if( 'FAS_AREC' == $variable ) $destination_filename = 'a_word_fluency.wav';
    else if( 'FAS_SREC' == $variable ) $destination_filename = 's_word_fluency.wav';
    else if( 'STP_DOTREC' == $variable ) $destination_filename = 'stroop_dot.wav';
    else if( 'STP_WORREC' == $variable ) $destination_filename = 'stroop_word.wav';
    else if( 'STP_COLREC' == $variable ) $destination_filename = 'stroop_colour.wav';
    else if( 'COG_ALPTME_REC2' == $variable ) $destination_filename = 'alphabet.wav';
    else if( 'COG_ALTTME_REC' == $variable ) $destination_filename = 'mental_alternation.wav';
    else if( 'COG_ANMLLLIST_REC' == $variable ) $destination_filename = 'animal_fluency.wav';
    else if( 'COG_CNTTMEREC' == $variable ) $destination_filename = 'counting.wav';
    else if( 'COG_WRDLST2_REC' == $variable ) $destination_filename = 'delayed_word_list.wav';
    else if( 'COG_WRDLSTREC' == $variable ) $destination_filename = 'immediate_word_list.wav';
  }
  else if( 'tracking' == $cohort )
  {
    // tracking phase names refer to the application, study phase and instance:
    // sabretooth_f1-live, sabretooth_f2-live, sabretooth_c1-live, etc
    if( 0 === preg_match( '/sabretooth_(bl|cb|[bf][1-9])-live/', $phase_name, $matches ) )
    {
      if( VERBOSE ) output( sprintf(
        'Ignoring invalid audio file: "%s"',
        $filename
      ) );
      if( !TEST_ONLY && !KEEP_FILES ) move_from_temporary_to_invalid( $filename );
      continue;
    }

    $code = $matches[1];

    if( 'bl' == $code || 'f' == substr( $code, 0, 1 ) )
    {
      // bl is baseline (phase 1), otherwise f1, f2, etc...
      $phase = 'bl' == $code ? 1 : intval( substr( $code, 1, 1 ) ) + 1;
      $study = 'clsa';
    }
    else if( 'cb' == $code || 'b' == substr( $code, 0, 1 ) )
    {
      // cb is baseline (phase 1), otherwise c1, c2, etc...
      $phase = 'cb' == $code ? 1 : intval( substr( $code, 1, 1 ) ) + 1;
      $study = 'covid_brain';
    }
    else
    {
      if( VERBOSE ) output( sprintf(
        'Ignoring invalid audio file "%s"',
        $filename
      ) );
      if( !TEST_ONLY && !KEEP_FILES ) move_from_temporary_to_invalid( $filename );
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
        else fatal_error( sprintf( 'Invalid filename "%s"', $filename ) );

        // add the operator tag if the audio is of the operator (in)
        if( !is_null( $destination_filename ) && $operator ) $destination_filename .= '-operator';
      }
    }
  }

  if( !is_null( $study ) && !is_null( $phase ) && !is_null( $destination_filename ) )
  {
    $destination_directory = sprintf(
      '%s/raw/%s/%s/audio/%s',
      DATA_DIR,
      $study,
      $phase,
      $uid
    );

    // make sure the directory exists (recursively)
    if( !is_dir( $destination_directory ) ) mkdir( $destination_directory, 0755, true );

    // determine the destination filename based on 
    $destination = sprintf( '%s/%s.wav', $destination_directory, $destination_filename );
    if( TEST_ONLY ? true : copy( $filename, $destination ) )
    {
      if( VERBOSE ) output( sprintf( '"%s" => "%s"', $filename, $destination ) );
      if( !TEST_ONLY && !KEEP_FILES ) unlink( $filename );
      $file_count++;
    }
    else
    {
      if( VERBOSE ) output( sprintf(
        'Failed to copy "%s" to "%s"',
        $filename,
        $destination
      ) );
      if( !TEST_ONLY && !KEEP_FILES ) move_from_temporary_to_invalid( $filename );
    }
  }
  else
  {
    if( VERBOSE ) output( sprintf(
      'Unable to process file "%s"',
      $filename
    ) );
    if( !TEST_ONLY && !KEEP_FILES ) move_from_temporary_to_invalid( $filename );
  }
}

// now remove all empty directories
foreach( glob( sprintf( '%s/*/audio/*/*', $base_dir ) ) as $dirname )
{
  if( is_dir( $dirname ) ) remove_dir( $dirname );
}

// any remaining files are to be moved to the invalid directory for data cleaning
foreach( glob( sprintf( '%s/*/audio/*/*', $base_dir ) ) as $dirname )
{
  move_from_temporary_to_invalid( $dirname );
}

output( sprintf(
  'Done, %d files %stransferred',
  $file_count,
  TEST_ONLY ? 'would be ' : ''
) );

exit( 0 );
