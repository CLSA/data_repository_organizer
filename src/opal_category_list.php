<?php

// post download function used by all carotid intima files
$cimt_post_download_function = function( $filename ) {
  $anonymized_filename = preg_replace(
    ['#/raw/#', '#\.gz$#'],
    ['/anonymized/', ''],
    $filename
  );

  $directory = dirname( $anonymized_filename );
  if( !is_dir( $directory ) ) mkdir( $directory, 0755, true );
  copy( $filename, $anonymized_filename.'.gz' );
  if( 0 < filesize( $anonymized_filename.'.gz' ) )
  {
    exec( sprintf( 'gzip -d -f %s.gz', $anonymized_filename ) );
    exec( sprintf( 'php /usr/local/lib/data_librarian/src/anonymize.php -t cimt %s', $anonymized_filename ) );
    exec( sprintf( 'gzip %s', $anonymized_filename ) );
  }
};

// post download function used by all dxa files
$dxa_post_download_function = function( $filename ) {
  $anonymized_filename = str_replace( '/raw/', '/anonymized/', $filename );
  $directory = dirname( $anonymized_filename );
  if( !is_dir( $directory ) ) mkdir( $directory, 0755, true );
  copy( $filename, $anonymized_filename );
  if( 0 < filesize( $anonymized_filename ) )
  {
    exec( sprintf( 'php src/anonymize.php -t dxa %s', $anonymized_filename ) );
  }

  // wbody BCA and BMD images need to be converted to jpeg
  $matches = [];
  if( preg_match( '/wbody_(bca|bmd)/', $filename, $matches ) )
  {
    $type = $matches[1];
    $image_filename = preg_replace( ['#/raw/#', '#\.xml$#'], ['/supplementary/', '.jpeg'], $filename );

    // convert from dcm to jpeg
    $output = [];
    $result_code = NULL;
    exec( sprintf( 'dcmj2pnm --write-jpeg %s %s', $filename, $image_filename ), $output, $result_code );
    if( 0 < $result_code )
    {
      // there was an error, so throw away any generated file
      if( file_exists( $image_filename ) ) unlink( $image_filename );
    }
    else
    {
      // crop the image (box based on BCA or BMA)
      exec( sprintf(
        'convert %s -crop %s +repage %s',
        $image_filename,
        'bca' == $type ? '607x872+489+136' : '334x757+492+176',
        $image_filename
      ) );
    }
  }
};

// post download function used by all dxa files
$dxa_wbody_post_download_function = function( $filename ) {
  $anonymized_filename = str_replace( '/raw/', '/anonymized/', $filename );
  $directory = dirname( $anonymized_filename );
  if( !is_dir( $directory ) ) mkdir( $directory, 0755, true );
  copy( $filename, $anonymized_filename );
  if( 0 < filesize( $anonymized_filename ) )
  {
    exec( sprintf( 'php src/anonymize.php -t dxa %s', $anonymized_filename ) );
  }
};

$category_list = [
  'cdtt' => [
    '3' => [
      'name' => 'cdtt',
      'datasource' => 'clsa-dcs',
      'table' => 'CDTT',
      'variable' => 'RESULT_FILE',
      'filename' => 'result_file.xls',
    ],
    '4' => [
      'name' => 'cdtt',
      'datasource' => 'clsa-dcs',
      'table' => 'CDTT',
      'variable' => 'RESULT_FILE',
      'filename' => 'result_file.xls',
    ],
  ],
  'choice_rt' => [
    'all' => [
      'name' => 'choice_rt',
      'datasource' => 'clsa-dcs',
      'table' => 'CognitiveTest',
      'variable' => 'RES_RESULT_FILE',
      'filename' => 'result_file.csv',
    ],
  ],
  'ecg' => [
    'all' => [
      'name' => 'ecg',
      'datasource' => 'clsa-dcs',
      'table' => 'ECG',
      'variable' => 'RES_XML_FILE',
      'filename' => 'ecg.xml',
      'post_download_function' => function( $filename ) {
        $anonymized_filename = preg_replace( '#/raw/#', '/anonymized/', $filename );
        $directory = dirname( $anonymized_filename );
        if( !is_dir( $directory ) ) mkdir( $directory, 0755, true );

        copy( $filename, $anonymized_filename );
        if( 0 < filesize( $anonymized_filename ) )
        {
          exec( sprintf(
            'php /usr/local/lib/data_librarian/src/anonymize.php -t ecg %s',
            $anonymized_filename
          ) );
        }

        if( 0 < filesize( $filename ) )
        {
          $image_filename = preg_replace( ['#/raw/#', '#\.xml$#'], ['/supplementary/', '.jpeg'], $filename );
          $directory = dirname( $image_filename );
          if( !is_dir( $directory ) ) mkdir( $directory, 0755, true );

          exec( sprintf(
            'php /usr/local/lib/data_librarian/src/plot_ecg.php -r %s %s',
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
    ],
    '3' => [
      'name' => 'frax',
      'datasource' => 'clsa-dcs',
      'table' => 'Frax',
      'variable' => 'RES_RESULT_FILE',
      'filename' => 'frax.txt',
    ],
    '4' => [
      'name' => 'frax',
      'datasource' => 'clsa-dcs',
      'table' => 'Frax',
      'variable' => 'RES_RESULT_FILE',
      'filename' => 'frax.txt',
    ],
  ],
  'stroop_dot' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'STP_DOTREC_DCS',
      'filename' => 'stroop_dot.wav',
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'STP_DOTREC_COF1',
      'filename' => 'stroop_dot.wav',
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'STP_DOTREC_COF2',
      'filename' => 'stroop_dot.wav',
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'STP_DOTREC_COF3',
      'filename' => 'stroop_dot.wav',
    ],
  ],
  'stroop_word' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'STP_WORREC_DCS',
      'filename' => 'stroop_word.wav',
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'STP_WORREC_COF1',
      'filename' => 'stroop_word.wav',
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'STP_WORREC_COF2',
      'filename' => 'stroop_word.wav',
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'STP_WORREC_COF3',
      'filename' => 'stroop_word.wav',
    ],
  ],
  'stroop_colour' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'STP_COLREC_DCS',
      'filename' => 'stroop_colour.wav',
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'STP_COLREC_COF1',
      'filename' => 'stroop_colour.wav',
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'STP_COLREC_COF2',
      'filename' => 'stroop_colour.wav',
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'STP_COLREC_COF3',
      'filename' => 'stroop_colour.wav',
    ],
  ],
  'f_word_fluency' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'FAS_FREC_DCS',
      'filename' => 'f_word_fluency.wav',
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'FAS_FREC_COF1',
      'filename' => 'f_word_fluency.wav',
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'FAS_FREC_COF2',
      'filename' => 'f_word_fluency.wav',
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'FAS_FREC_COF3',
      'filename' => 'f_word_fluency.wav',
    ],
  ],
  'a_word_fluency' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'FAS_AREC_DCS',
      'filename' => 'a_word_fluency.wav',
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'FAS_AREC_COF1',
      'filename' => 'a_word_fluency.wav',
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'FAS_AREC_COF2',
      'filename' => 'a_word_fluency.wav',
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'FAS_AREC_COF3',
      'filename' => 'a_word_fluency.wav',
    ],
  ],
  's_word_fluency' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'FAS_SREC_DCS',
      'filename' => 's_word_fluency.wav',
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'NeuropsychologicalBattery',
      'variable' => 'FAS_SREC_COF1',
      'filename' => 's_word_fluency.wav',
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'FAS_SREC_COF2',
      'filename' => 's_word_fluency.wav',
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-dcs',
      'table' => 'StroopFAS',
      'variable' => 'FAS_SREC_COF3',
      'filename' => 's_word_fluency.wav',
    ],
  ],
  'alphabet' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ALPTME_REC2_COM',
      'filename' => 'alphabet.wav',
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ALPTME_REC2_COF1',
      'filename' => 'alphabet.wav',
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ALPTME_REC2_COF2',
      'filename' => 'alphabet.wav',
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ALPTME_REC2_COF3',
      'filename' => 'alphabet.wav',
    ],
  ],
  'mental_alternation' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ALTTME_REC_COM',
      'filename' => 'mental_alternation.wav',
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ALTTME_REC_COF1',
      'filename' => 'mental_alternation.wav',
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ALTTME_REC_COF2',
      'filename' => 'mental_alternation.wav',
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ALTTME_REC_COF3',
      'filename' => 'mental_alternation.wav',
    ],
  ],
  'animal_fluency' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ANMLLLIST_REC_COM',
      'filename' => 'animal_fluency.wav',
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ANMLLLIST_REC_COF1',
      'filename' => 'animal_fluency.wav',
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ANMLLLIST_REC_COF2',
      'filename' => 'animal_fluency.wav',
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_ANMLLLIST_REC_COF3',
      'filename' => 'animal_fluency.wav',
    ],
  ],
  'counting' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_CNTTMEREC_COM',
      'filename' => 'counting.wav',
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_CNTTMEREC_COF1',
      'filename' => 'counting.wav',
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_CNTTMEREC_COF2',
      'filename' => 'counting.wav',
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_CNTTMEREC_COF3',
      'filename' => 'counting.wav',
    ],
  ],
  'delayed_word_list' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_WRDLST2_REC_COM',
      'filename' => 'delayed_word_list.wav',
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_WRDLST2_REC_COF1',
      'filename' => 'delayed_word_list.wav',
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_WRDLST2_REC_COF2',
      'filename' => 'delayed_word_list.wav',
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_WRDLST2_REC_COF3',
      'filename' => 'delayed_word_list.wav',
    ],
  ],
  'immediate_word_list' => [
    '1' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_WRDLSTREC_COM',
      'filename' => 'immediate_word_list.wav',
    ],
    '2' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_WRDLSTREC_COF1',
      'filename' => 'immediate_word_list.wav',
    ],
    '3' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_WRDLSTREC_COF2',
      'filename' => 'immediate_word_list.wav',
    ],
    '4' => [
      'name' => 'audio',
      'datasource' => 'clsa-inhome',
      'table' => 'InHome_2',
      'variable' => 'COG_WRDLSTREC_COF3',
      'filename' => 'immediate_word_list.wav',
    ],
  ],
  'spirometry_flow' => [
    'all' => [
      'name' => 'spirometry',
      'datasource' => 'clsa-dcs',
      'table' => 'Spirometry',
      'variable' => 'Measure.RES_FLOW_VALUES',
      'filename' => 'spirometry_flow_<N>.txt',
    ],
  ],
  'spirometry_volume' => [
    'all' => [
      'name' => 'spirometry',
      'datasource' => 'clsa-dcs',
      'table' => 'Spirometry',
      'variable' => 'Measure.RES_VOLUME_VALUES',
      'filename' => 'spirometry_volume_<N>.txt',
    ],
  ],
  'spirometry_report' => [
    '2' => [
      'name' => 'spirometry',
      'datasource' => 'clsa-dcs',
      'table' => 'Spirometry',
      'variable' => 'Measure.RES_REPORT',
      'filename' => 'report.pdf', // this data isn't actually repeated, so no <N> is included,
    ],
    '3' => [
      'name' => 'spirometry',
      'datasource' => 'clsa-dcs',
      'table' => 'Spirometry',
      'variable' => 'Measure.RES_REPORT',
      'filename' => 'report.pdf', // this data isn't actually repeated, so no <N> is included,
    ],
    '4' => [
      'name' => 'spirometry',
      'datasource' => 'clsa-dcs',
      'table' => 'Spirometry',
      'variable' => 'Measure.RES_REPORT',
      'filename' => 'report.pdf', // this data isn't actually repeated, so no <N> is included,
    ],
  ],
  'cineloop1' => [ // baseline had three cineloops
    '1' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.CINELOOP_1',
      'filename' => 'cineloop1_<N>.dcm.gz',
      'post_download_function' => $cimt_post_download_function,
    ],
  ],
  'cineloop2' => [
    '1' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.CINELOOP_2',
      'filename' => 'cineloop2_<N>.dcm.gz',
      'post_download_function' => $cimt_post_download_function,
    ],
  ],
  'cineloop3' => [
    '1' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.CINELOOP_3',
      'filename' => 'cineloop3_<N>.dcm.gz',
      'post_download_function' => $cimt_post_download_function,
    ],
  ],
  'cineloop' => [ // after baseline we only have one cineloop
    '2' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.CINELOOP_1',
      'filename' => 'cineloop_<N>.dcm.gz',
      'post_download_function' => $cimt_post_download_function,
    ],
    '3' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.CINELOOP_1',
      'filename' => 'cineloop_<N>.dcm.gz',
      'post_download_function' => $cimt_post_download_function,
    ],
    '4' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.CINELOOP_1',
      'filename' => 'cineloop_<N>.dcm.gz',
      'post_download_function' => $cimt_post_download_function,
    ],
  ],
  'plaque_cineloop' => [
    '1' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'Plaque',
      'variable' => 'Measure.CINELOOP_1',
      'filename' => 'plaque_cineloop_<N>.dcm.gz',
      'post_download_function' => $cimt_post_download_function,
    ],
  ],
  'us_report' => [
    '1' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.SR',
      'filename' => 'report.dcm' // this data isn't actually repeated, so no <N> is included,
    ],
    '2' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.SR_1',
      'filename' => 'report.dcm' // this data isn't actually repeated, so no <N> is included,
    ],
    '3' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.SR_1',
      'filename' => 'report.dcm' // this data isn't actually repeated, so no <N> is included,
    ],
    '4' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.SR_1',
      'filename' => 'report.dcm' // this data isn't actually repeated, so no <N> is included,
    ],
  ],
  'still_image' => [ // baseline only has one still image
    '1' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE',
      'filename' => 'still_<N>.dcm.gz',
      'post_download_function' => $cimt_post_download_function,
    ],
  ],
  'still_image_1' => [ // beyond baseline had three still images
    '2' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE_1',
      'filename' => 'still1_<N>.dcm.gz',
      'post_download_function' => $cimt_post_download_function,
    ],
    '3' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE_1',
      'filename' => 'still1_<N>.dcm.gz',
      'post_download_function' => $cimt_post_download_function,
    ],
    '4' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE_1',
      'filename' => 'still1_<N>.dcm.gz',
      'post_download_function' => $cimt_post_download_function,
    ],
  ],
  'still_image_2' => [
    '2' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE_2',
      'filename' => 'still2_<N>.dcm.gz',
      'post_download_function' => $cimt_post_download_function,
    ],
    '3' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE_2',
      'filename' => 'still2_<N>.dcm.gz',
      'post_download_function' => $cimt_post_download_function,
    ],
    '4' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE_2',
      'filename' => 'still2_<N>.dcm.gz',
      'post_download_function' => $cimt_post_download_function,
    ],
  ],
  'still_image_3' => [
    '2' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE_3',
      'filename' => 'still3_<N>.dcm.gz',
      'post_download_function' => $cimt_post_download_function,
    ],
    '3' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE_3',
      'filename' => 'still3_<N>.dcm.gz',
      'post_download_function' => $cimt_post_download_function,
    ],
    '4' => [
      'name' => 'carotid_intima',
      'datasource' => 'clsa-dcs-images',
      'table' => 'CarotidIntima',
      'variable' => 'Measure.STILL_IMAGE_3',
      'filename' => 'still3_<N>.dcm.gz',
      'post_download_function' => $cimt_post_download_function,
    ],
  ],
  'dxa_hip' => [
    'all' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'DualHipBoneDensity',
      'variable' => 'Measure.RES_HIP_DICOM',
      'filename' => 'dxa_hip_<N>.dcm',
    ],
  ],
  'dxa_forearm' => [
    'all' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'ForearmBoneDensity',
      'variable' => 'RES_FA_DICOM',
      'filename' => 'dxa_forearm.dcm',
    ],
  ],
  'dxa_lateral' => [
    'all' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'LateralBoneDensity',
      'variable' => 'RES_SEL_DICOM_MEASURE',
      'filename' => 'dxa_lateral.dcm',
      'post_download_function' => $dxa_post_download_function,
    ],
  ],
  'dxa_lateral_ot' => [
    'all' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'LateralBoneDensity',
      'variable' => 'RES_SEL_DICOM_OT',
      'filename' => 'dxa_lateral_ot.dcm',
    ],
  ],
  'dxa_lateral_pr' => [
    'all' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'LateralBoneDensity',
      'variable' => 'RES_SEL_DICOM_PR',
      'filename' => 'dxa_lateral_pr.dcm',
    ],
  ],
  'dxa_spine' => [
    '2' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'SpineBoneDensity',
      'variable' => 'RES_SP_DICOM',
      'filename' => 'dxa_spine.dcm',
    ],
    '3' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'SpineBoneDensity',
      'variable' => 'RES_SP_DICOM',
      'filename' => 'dxa_spine.dcm',
    ],
  ],
  'dxa_wbody_bmd' => [
    'all' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'LateralBoneDensity',
      'variable' => 'RES_WB_DICOM_1',
      'filename' => 'dxa_wbody_bmd.dcm',
      'post_download_function' => $dxa_post_download_function,
    ],
  ],
  'dxa_wbody_bca' => [
    'all' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'LateralBoneDensity',
      'variable' => 'RES_WB_DICOM_2',
      'filename' => 'dxa_wbody_bca.dcm',
      'post_download_function' => $dxa_post_download_function,
    ],
  ],
  'dxa_hip_recovery_left' => [
    '1' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'HipRecoveryLeft',
      'variable' => 'RES_HIP_DICOM',
      'filename' => 'dxa_hip_recovery_left.dcm',
    ],
    '2' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'HipRecoveryLeft',
      'variable' => 'RES_HIP_DICOM',
      'filename' => 'dxa_hip_recovery_left.dcm',
    ],
  ],
  'dxa_hip_recovery_right' => [
    '1' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'HipRecoveryRight',
      'variable' => 'RES_HIP_DICOM',
      'filename' => 'dxa_hip_recovery_right.dcm',
    ],
    '2' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'HipRecoveryRight',
      'variable' => 'RES_HIP_DICOM',
      'filename' => 'dxa_hip_recovery_right.dcm',
    ],
  ],
  'dxa_lateral_recovery' => [
    '1' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'LateralRecovery',
      'variable' => 'RES_SEL_DICOM_MEASURE',
      'filename' => 'dxa_lateral_recovery.dcm',
    ],
    '2' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'LateralRecovery',
      'variable' => 'RES_SEL_DICOM_MEASURE',
      'filename' => 'dxa_lateral_recovery.dcm',
    ],
  ],
  'dxa_wbody_recovery' => [
    '1' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'WbodyRecovery',
      'variable' => 'RES_WB_DICOM_1',
      'filename' => 'dxa_wbody_recovery.dcm',
    ],
    '2' => [
      'name' => 'dxa',
      'datasource' => 'clsa-dcs-images',
      'table' => 'WbodyRecovery',
      'variable' => 'RES_WB_DICOM_1',
      'filename' => 'dxa_wbody_recovery.dcm',
    ],
  ],
  'retinal' => [
    '1' => [
      'name' => 'retinal',
      'datasource' => 'clsa-dcs-images',
      'table' => 'RetinalScan',
      'variable' => 'Measure.EYE',
      'filename' => 'retinal_<N>.jpeg',
    ],
  ],
  'retinal_left' => [
    '2' => [
      'name' => 'retinal',
      'datasource' => 'clsa-dcs-images',
      'table' => 'RetinalScanLeft',
      'variable' => 'EYE',
      'filename' => 'retinal_left.jpeg',
    ],
    '3' => [
      'name' => 'retinal',
      'datasource' => 'clsa-dcs-images',
      'table' => 'RetinalScanLeft',
      'variable' => 'EYE',
      'filename' => 'retinal_left.jpeg',
    ],
    '4' => [
      'name' => 'retinal',
      'datasource' => 'clsa-dcs-images',
      'table' => 'RetinalScanLeft',
      'variable' => 'EYE',
      'filename' => 'retinal_left.jpeg',
    ],
  ],
  'retinal_right' => [
    '2' => [
      'name' => 'retinal',
      'datasource' => 'clsa-dcs-images',
      'table' => 'RetinalScanRight',
      'variable' => 'EYE',
      'filename' => 'retinal_right.jpeg',
    ],
    '3' => [
      'name' => 'retinal',
      'datasource' => 'clsa-dcs-images',
      'table' => 'RetinalScanRight',
      'variable' => 'EYE',
      'filename' => 'retinal_right.jpeg',
    ],
    '4' => [
      'name' => 'retinal',
      'datasource' => 'clsa-dcs-images',
      'table' => 'RetinalScanRight',
      'variable' => 'EYE',
      'filename' => 'retinal_right.jpeg',
    ],
  ],
];
