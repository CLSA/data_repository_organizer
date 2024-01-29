<?php
require_once( 'common.php' );

/**
 * Post link function used by all us_report "SR" files
 * 
 * This function will parse the cIMT values from the SR report file and add them to the report_summary.csv
 * file found in the root of the supplementary's carotid_intima folder.
 */
$us_report_post_link_function = function( $filename, $link ) {
  // determine the report summary filename
  $matches = [];
  if( !preg_match( '#raw/([^/]+/[0-9]+)/carotid_intima#', $filename, $matches ) ) return;
  $summary_filename = sprintf( '/data/supplementary/%s/carotid_intima/report_summary.csv', $matches[1] );

  // get the UID from the filename
  $matches = [];
  $uid = preg_match( '#/([A-Z0-9]+)/report_#', $filename, $matches ) ? $matches[1] : NULL;

  // get the side from the link
  $matches = [];
  $side = preg_match( '#report_(.+)\.dcm.gz#', $link, $matches ) ? $matches[1] : NULL;

  // only continue if we have a uid and side
  if( is_null( $uid ) || is_null( $side ) ) return;

  // remove this participant-side's entry in the report summary
  exec( sprintf(
    'sed -i "/^%s,%s,/d" %s',
    $uid,
    $side,
    $summary_filename
  ) );

  // decompress the report file
  $decompressed_filename = decompress_file( $filename );

  // only continue if we successfully got a decompressed file
  if( is_null( $decompressed_filename ) ) return;

  // get the report values from the dcm file
  $summary_data = [];
  $result_code = NULL;
  exec(
    sprintf( 'php %s/get_us_pr_data.php %s', __DIR__, $decompressed_filename ),
    $summary_data,
    $result_code
  );

  // delete the temporary decompressed file now that we're done with it
  unlink( $decompressed_filename );

  if( 0 == $result_code )
  {
    // add the uid and side to each line parsed from the report file
    foreach( $summary_data as $index => $line )
      $summary_data[$index] = sprintf( '%s,%s,%s', $uid, $side, $line );

    // now find where to insert the new data
    $insert_index = NULL;
    foreach( explode( "\n", file_get_contents( $summary_filename ) ) as $index => $line )
    {
      // skip the header
      if( 0 == $index ) continue;

      $line_uid = substr( $line, 0, 7 );
      if( $line_uid > $uid )
      {
        $insert_index = $index;
        break;
      }
    }

    if( is_null( $insert_index ) )
    {
      // add the data to the end of the file
      exec( sprintf(
        "echo '%s' >> %s",
        implode( "\n", $summary_data ), // join the data with newlines
        $summary_filename
      ) );
    }
    else
    {
      // insert data at the given index
      exec( sprintf(
        'sed -i "%d i %s" %s',
        $insert_index + 1,
        implode( '\n', $summary_data ), // join the data with \n (as a string)
        $summary_filename
      ) );
    }
  }
};

/**
 * Post download function used by all dxa files
 * 
 * This will generate jpeg versions of forearm, hip and wbody DICOM files for release to participants.
 * It will also create jpeg versions of forearm DICOM files for release to reserachers.
 */
$dxa_post_download_function = function( $filename, $cenozo_db ) {
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
      ['#/raw/#', '#\.dcm$#'],
      ['/supplementary/', '.participant.jpeg'],
      $filename
    );
    $researcher_image_filename = preg_replace(
      ['#/raw/#', '#\.dcm$#'],
      ['/supplementary/', '.jpeg'],
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
        'php %s/create_dxa_for_participant.php -t %s -i %s %s %s',
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
          'php %s/create_dxa_for_researcher.php -t %s %s %s',
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
};

$category_list = [
  'cdtt' => [
    '3' => [
      'name' => 'cdtt',
      'datasource' => 'clsa-dcs',
      'table' => 'CDTT',
      'variable' => 'RESULT_FILE',
      'filename' => 'result_file.xls',
      'db_required' => false,
    ],
    '4' => [
      'name' => 'cdtt',
      'datasource' => 'clsa-dcs',
      'table' => 'CDTT',
      'variable' => 'RESULT_FILE',
      'filename' => 'result_file.xls',
      'db_required' => false,
    ],
  ],
  'choice_rt' => [
    'all' => [
      'name' => 'choice_rt',
      'datasource' => 'clsa-dcs',
      'table' => 'CognitiveTest',
      'variable' => 'RES_RESULT_FILE',
      'filename' => 'result_file.csv',
      'db_required' => false,
    ],
  ],
  'ecg' => [
    'all' => [
      'name' => 'ecg',
      'datasource' => 'clsa-dcs',
      'table' => 'ECG',
      'variable' => 'RES_XML_FILE',
      'filename' => 'ecg.xml',
      'db_required' => false,
      'post_download_function' => function( $filename ) {
        if( 0 < filesize( $filename ) )
        {
          $image_filename = preg_replace( ['#/raw/#', '#\.xml$#'], ['/supplementary/', '.jpeg'], $filename );
          $directory = dirname( $image_filename );
          if( !is_dir( $directory ) ) mkdir( $directory, 0755, true );

          exec( sprintf(
            'php %s/plot_ecg.php -r %s %s',
            __DIR__,
            $filename,
            $image_filename
          ) );
        }
      },
    ],
  ],
  'frax' => [
    '2' => [
      'name' => 'frax',
      'datasource' => 'clsa-dcs',
      'table' => 'Frax',
      'variable' => 'RES_RESULT_FILE',
      'filename' => 'frax.txt',
      'db_required' => false,
    ],
    '3' => [
      'name' => 'frax',
      'datasource' => 'clsa-dcs',
      'table' => 'Frax',
      'variable' => 'RES_RESULT_FILE',
      'filename' => 'frax.txt',
      'db_required' => false,
    ],
    '4' => [
      'name' => 'frax',
      'datasource' => 'clsa-dcs',
      'table' => 'Frax',
      'variable' => 'RES_RESULT_FILE',
      'filename' => 'frax.txt',
      'db_required' => false,
    ],
  ],
  'stroop_dot' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'STP_DOTREC_DCS',
      'filename' => 'stroop_dot.wav',
      'db_required' => false,
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'STP_DOTREC_COF1',
      'filename' => 'stroop_dot.wav',
      'db_required' => false,
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'STP_DOTREC_COF2',
      'filename' => 'stroop_dot.wav',
      'db_required' => false,
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'STP_DOTREC_COF3',
      'filename' => 'stroop_dot.wav',
      'db_required' => false,
    ],
  ],
  'stroop_word' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'STP_WORREC_DCS',
      'filename' => 'stroop_word.wav',
      'db_required' => false,
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'STP_WORREC_COF1',
      'filename' => 'stroop_word.wav',
      'db_required' => false,
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'STP_WORREC_COF2',
      'filename' => 'stroop_word.wav',
      'db_required' => false,
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'STP_WORREC_COF3',
      'filename' => 'stroop_word.wav',
      'db_required' => false,
    ],
  ],
  'stroop_colour' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'STP_COLREC_DCS',
      'filename' => 'stroop_colour.wav',
      'db_required' => false,
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'STP_COLREC_COF1',
      'filename' => 'stroop_colour.wav',
      'db_required' => false,
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'STP_COLREC_COF2',
      'filename' => 'stroop_colour.wav',
      'db_required' => false,
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'STP_COLREC_COF3',
      'filename' => 'stroop_colour.wav',
      'db_required' => false,
    ],
  ],
  'f_word_fluency' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'FAS_FREC_DCS',
      'filename' => 'f_word_fluency.wav',
      'db_required' => false,
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'FAS_FREC_COF1',
      'filename' => 'f_word_fluency.wav',
      'db_required' => false,
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'FAS_FREC_COF2',
      'filename' => 'f_word_fluency.wav',
      'db_required' => false,
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'FAS_FREC_COF3',
      'filename' => 'f_word_fluency.wav',
      'db_required' => false,
    ],
  ],
  'a_word_fluency' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'FAS_AREC_DCS',
      'filename' => 'a_word_fluency.wav',
      'db_required' => false,
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'FAS_AREC_COF1',
      'filename' => 'a_word_fluency.wav',
      'db_required' => false,
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'FAS_AREC_COF2',
      'filename' => 'a_word_fluency.wav',
      'db_required' => false,
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'FAS_AREC_COF3',
      'filename' => 'a_word_fluency.wav',
      'db_required' => false,
    ],
  ],
  's_word_fluency' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'FAS_SREC_DCS',
      'filename' => 's_word_fluency.wav',
      'db_required' => false,
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'FAS_SREC_COF1',
      'filename' => 's_word_fluency.wav',
      'db_required' => false,
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'FAS_SREC_COF2',
      'filename' => 's_word_fluency.wav',
      'db_required' => false,
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'FAS_SREC_COF3',
      'filename' => 's_word_fluency.wav',
      'db_required' => false,
    ],
  ],
  'alphabet' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ALPTME_REC2_COM',
      'filename' => 'alphabet.wav',
      'db_required' => false,
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ALPTME_REC2_COF1',
      'filename' => 'alphabet.wav',
      'db_required' => false,
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ALPTME_REC2_COF2',
      'filename' => 'alphabet.wav',
      'db_required' => false,
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ALPTME_REC2_COF3',
      'filename' => 'alphabet.wav',
      'db_required' => false,
    ],
  ],
  'mental_alternation' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ALTTME_REC_COM',
      'filename' => 'mental_alternation.wav',
      'db_required' => false,
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ALTTME_REC_COF1',
      'filename' => 'mental_alternation.wav',
      'db_required' => false,
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ALTTME_REC_COF2',
      'filename' => 'mental_alternation.wav',
      'db_required' => false,
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ALTTME_REC_COF3',
      'filename' => 'mental_alternation.wav',
      'db_required' => false,
    ],
  ],
  'animal_fluency' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ANMLLLIST_REC_COM',
      'filename' => 'animal_fluency.wav',
      'db_required' => false,
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ANMLLLIST_REC_COF1',
      'filename' => 'animal_fluency.wav',
      'db_required' => false,
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ANMLLLIST_REC_COF2',
      'filename' => 'animal_fluency.wav',
      'db_required' => false,
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ANMLLLIST_REC_COF3',
      'filename' => 'animal_fluency.wav',
      'db_required' => false,
    ],
  ],
  'counting' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_CNTTMEREC_COM',
      'filename' => 'counting.wav',
      'db_required' => false,
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_CNTTMEREC_COF1',
      'filename' => 'counting.wav',
      'db_required' => false,
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_CNTTMEREC_COF2',
      'filename' => 'counting.wav',
      'db_required' => false,
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_CNTTMEREC_COF3',
      'filename' => 'counting.wav',
      'db_required' => false,
    ],
  ],
  'delayed_word_list' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_WRDLST2_REC_COM',
      'filename' => 'delayed_word_list.wav',
      'db_required' => false,
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_WRDLST2_REC_COF1',
      'filename' => 'delayed_word_list.wav',
      'db_required' => false,
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_WRDLST2_REC_COF2',
      'filename' => 'delayed_word_list.wav',
      'db_required' => false,
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_WRDLST2_REC_COF3',
      'filename' => 'delayed_word_list.wav',
      'db_required' => false,
    ],
  ],
  'immediate_word_list' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_WRDLSTREC_COM',
      'filename' => 'immediate_word_list.wav',
      'db_required' => false,
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_WRDLSTREC_COF1',
      'filename' => 'immediate_word_list.wav',
      'db_required' => false,
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_WRDLSTREC_COF2',
      'filename' => 'immediate_word_list.wav',
      'db_required' => false,
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_WRDLSTREC_COF3',
      'filename' => 'immediate_word_list.wav',
      'db_required' => false,
    ],
  ],
  'spirometry_flow' => [
    'all' => [
      'name' => 'spirometry',
      'datasource' => 'clsa-dcs',
      'table' => 'Spirometry',
      'variable' => 'Measure.RES_FLOW_VALUES',
      'filename' => 'spirometry_flow_<N>.txt',
      'db_required' => false,
    ],
  ],
  'spirometry_volume' => [
    'all' => [
      'name' => 'spirometry',
      'datasource' => 'clsa-dcs',
      'table' => 'Spirometry',
      'variable' => 'Measure.RES_VOLUME_VALUES',
      'filename' => 'spirometry_volume_<N>.txt',
      'db_required' => false,
    ],
  ],
  'spirometry_report' => [
    '2' => [
      'name' => 'spirometry',
      'datasource' => 'clsa-dcs',
      'table' => 'Spirometry',
      'variable' => 'Measure.RES_REPORT',
      'filename' => 'report.pdf', // this data isn't actually repeated, so no <N> is included,
      'db_required' => false,
    ],
    '3' => [
      'name' => 'spirometry',
      'datasource' => 'clsa-dcs',
      'table' => 'Spirometry',
      'variable' => 'Measure.RES_REPORT',
      'filename' => 'report.pdf', // this data isn't actually repeated, so no <N> is included,
      'db_required' => false,
    ],
    '4' => [
      'name' => 'spirometry',
      'datasource' => 'clsa-dcs',
      'table' => 'Spirometry',
      'variable' => 'Measure.RES_REPORT',
      'filename' => 'report.pdf', // this data isn't actually repeated, so no <N> is included,
      'db_required' => false,
    ],
  ],
  'cineloop1' => [ // baseline had three cineloops
    '1' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.CINELOOP_1',
      'filename' => 'cineloop1_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
    ],
  ],
  'cineloop2' => [
    '1' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.CINELOOP_2',
      'filename' => 'cineloop2_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
    ],
  ],
  'cineloop3' => [
    '1' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.CINELOOP_3',
      'filename' => 'cineloop3_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
    ],
  ],
  'cineloop' => [ // after baseline we only have one cineloop
    '2' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.CINELOOP_1',
      'filename' => 'cineloop_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
    ],
    '3' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.CINELOOP_1',
      'filename' => 'cineloop_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
    ],
    '4' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.CINELOOP_1',
      'filename' => 'cineloop_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
    ],
  ],
  'plaque_cineloop' => [
    '1' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'Plaque',
      'variable' => 'Measure.CINELOOP_1',
      'filename' => 'plaque_cineloop_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
    ],
  ],
  'us_report' => [
    '1' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.SR',
      'filename' => 'report_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
      // 'post_link_function' => $us_report_post_link_function, TODO: uncomment once re-download is done
    ],
    '2' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.SR_1',
      'filename' => 'report_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
      'post_link_function' => $us_report_post_link_function,
    ],
    '3' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.SR_1',
      'filename' => 'report_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
      'post_link_function' => $us_report_post_link_function,
    ],
    '4' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.SR_1',
      'filename' => 'report_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
      'post_link_function' => $us_report_post_link_function,
    ],
  ],
  'still_image' => [ // baseline only has one still image
    '1' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE',
      'filename' => 'still_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
    ],
  ],
  'still_image_1' => [ // beyond baseline had three still images
    '2' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE_1',
      'filename' => 'still1_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
    ],
    '3' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE_1',
      'filename' => 'still1_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
    ],
    '4' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE_1',
      'filename' => 'still1_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
    ],
  ],
  'still_image_2' => [
    '2' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE_2',
      'filename' => 'still2_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
    ],
    '3' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE_2',
      'filename' => 'still2_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
    ],
    '4' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE_2',
      'filename' => 'still2_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
    ],
  ],
  'still_image_3' => [
    '2' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE_3',
      'filename' => 'still3_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
    ],
    '3' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE_3',
      'filename' => 'still3_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
    ],
    '4' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE_3',
      'filename' => 'still3_<N>.dcm.gz',
      'db_required' => false,
      'side' => 'Measure.SIDE',
    ],
  ],
  'dxa_hip' => [
    'all' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'DualHipBoneDensity',
      'variable' => 'Measure.RES_HIP_DICOM',
      'side' => 'Measure.OUTPUT_HIP_SIDE',
      'filename' => 'dxa_hip_<N>.dcm',
      'db_required' => true,
      'post_download_function' => $dxa_post_download_function,
    ],
  ],
  'dxa_forearm' => [
    'all' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'ForearmBoneDensity',
      'variable' => 'RES_FA_DICOM',
      'side' => 'OUTPUT_FA_SIDE',
      'filename' => 'dxa_forearm.dcm',
      'db_required' => true,
      'post_download_function' => $dxa_post_download_function,
    ],
  ],
  'dxa_lateral' => [
    'all' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'LateralBoneDensity',
      'variable' => 'RES_SEL_DICOM_MEASURE',
      'filename' => 'dxa_lateral.dcm',
      'db_required' => false,
    ],
  ],
  'dxa_lateral_ot' => [
    'all' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'LateralBoneDensity',
      'variable' => 'RES_SEL_DICOM_OT',
      'filename' => 'dxa_lateral_ot.dcm',
      'db_required' => false,
    ],
  ],
  'dxa_lateral_pr' => [
    'all' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'LateralBoneDensity',
      'variable' => 'RES_SEL_DICOM_PR',
      'filename' => 'dxa_lateral_pr.dcm',
      'db_required' => false,
    ],
  ],
  'dxa_spine' => [
    '2' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'SpineBoneDensity',
      'variable' => 'RES_SP_DICOM',
      'filename' => 'dxa_spine.dcm',
      'db_required' => false,
    ],
    '3' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'SpineBoneDensity',
      'variable' => 'RES_SP_DICOM',
      'filename' => 'dxa_spine.dcm',
      'db_required' => false,
    ],
    '4' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'SpineBoneDensity',
      'variable' => 'RES_SP_DICOM',
      'filename' => 'dxa_spine.dcm',
      'db_required' => false,
    ],
  ],
  'dxa_wbody_bmd' => [
    'all' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'WholeBodyBoneDensity',
      'variable' => 'RES_WB_DICOM_1',
      'filename' => 'dxa_wbody_bmd.dcm',
      'db_required' => true,
      'post_download_function' => $dxa_post_download_function,
    ],
  ],
  'dxa_wbody_bca' => [
    'all' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'WholeBodyBoneDensity',
      'variable' => 'RES_WB_DICOM_2',
      'filename' => 'dxa_wbody_bca.dcm',
      'db_required' => false,
    ],
  ],
  'dxa_hip_recovery_left' => [
    '1' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'HipRecoveryLeft',
      'variable' => 'RES_HIP_DICOM',
      'filename' => 'dxa_hip_recovery_left.dcm',
      'db_required' => false,
    ],
  ],
  'dxa_hip_recovery_right' => [
    '1' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'HipRecoveryRight',
      'variable' => 'RES_HIP_DICOM',
      'filename' => 'dxa_hip_recovery_right.dcm',
      'db_required' => false,
    ],
  ],
  'dxa_lateral_recovery' => [
    '1' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'LateralRecovery',
      'variable' => 'RES_SEL_DICOM_MEASURE',
      'filename' => 'dxa_lateral_recovery.dcm',
      'db_required' => false,
    ],
  ],
  'dxa_wbody_recovery' => [
    '1' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'WbodyRecovery',
      'variable' => 'RES_WB_DICOM_1',
      'filename' => 'dxa_wbody_recovery.dcm',
      'db_required' => false,
    ],
  ],
  'retinal' => [
    '1' => [
      'name' => 'retinal',
      'datasource' => 'clsa-dcs-images',
      'table' => 'RetinalScan',
      'variable' => 'Measure.EYE',
      'side' => 'Measure.SIDE',
      'filename' => 'retinal_<N>.jpeg',
      'db_required' => false,
    ],
  ],
  'retinal_left' => [
    '2' => [
      'name' => 'retinal',
      'datasource' => 'clsa-dcs-images',
      'table' => 'RetinalScanLeft',
      'variable' => 'EYE',
      'filename' => 'retinal_left.jpeg',
      'db_required' => false,
    ],
    '3' => [
      'name' => 'retinal',
      'datasource' => 'clsa-dcs-images',
      'table' => 'RetinalScanLeft',
      'variable' => 'EYE',
      'filename' => 'retinal_left.jpeg',
      'db_required' => false,
    ],
    '4' => [
      'name' => 'retinal',
      'datasource' => 'clsa-dcs-images',
      'table' => 'RetinalScanLeft',
      'variable' => 'EYE',
      'filename' => 'retinal_left.jpeg',
      'db_required' => false,
    ],
  ],
  'retinal_right' => [
    '2' => [
      'name' => 'retinal',
      'datasource' => 'clsa-dcs-images',
      'table' => 'RetinalScanRight',
      'variable' => 'EYE',
      'filename' => 'retinal_right.jpeg',
      'db_required' => false,
    ],
    '3' => [
      'name' => 'retinal',
      'datasource' => 'clsa-dcs-images',
      'table' => 'RetinalScanRight',
      'variable' => 'EYE',
      'filename' => 'retinal_right.jpeg',
      'db_required' => false,
    ],
    '4' => [
      'name' => 'retinal',
      'datasource' => 'clsa-dcs-images',
      'table' => 'RetinalScanRight',
      'variable' => 'EYE',
      'filename' => 'retinal_right.jpeg',
      'db_required' => false,
    ],
  ],
];
