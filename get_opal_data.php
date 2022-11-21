<?php
require_once 'common.php';

$base_dir = sprintf( '%s/%s', DATA_DIR, TEMPORARY_DIR );
$category_list = array(
  'cdtt' => array(
    'name' => 'cdtt',
    'datasource' => 'clsa-dcs',
    'table' => 'CDTT',
    'variable' => 'RESULT_FILE',
    'filename' => 'result_file.xls'
  ),
  'choice_rt' => array(
    'name' => 'choice_rt',
    'datasource' => 'clsa-dcs',
    'table' => 'CognitiveTest',
    'variable' => 'RES_RESULT_FILE',
    'filename' => 'result_file.csv'
  ),
  'ecg' => array(
    'name' => 'ecg',
    'datasource' => 'clsa-dcs',
    'table' => 'ECG',
    'variable' => 'RES_XML_FILE',
    'filename' => 'ecg.xml'
  ),
  'frax' => array(
    'name' => 'frax',
    'datasource' => 'clsa-dcs',
    'table' => 'Frax',
    'variable' => 'RES_RESULT_FILE',
    'filename' => 'frax.txt'
  ),
  'spirometry_flow' => array(
    'name' => 'spirometry',
    'datasource' => 'clsa-dcs',
    'table' => 'Spirometry',
    'variable' => 'Measure.RES_FLOW_VALUES',
    'filename' => 'spirometry_flow_<N>.txt'
  ),
  'spirometry_volume' => array(
    'name' => 'spirometry',
    'datasource' => 'clsa-dcs',
    'table' => 'Spirometry',
    'variable' => 'Measure.RES_VOLUME_VALUES',
    'filename' => 'spirometry_volume_<N>.txt'
  ),
  'spirometry_report' => array(
    'name' => 'spirometry',
    'datasource' => 'clsa-dcs',
    'table' => 'Spirometry',
    'variable' => 'Measure.RES_REPORT',
    'filename' => 'spirometry_report.pdf' // this data isn't actually repeated, so no <N> is included
  )
);
$possible_category_list = array_keys( $category_list );

if(
  3 != $argc ||
  !preg_match( '/^[0-9]$/', $argv[1] ) ||
  !in_array( $argv[2], $possible_category_list )
) {
  file_put_contents(
    'php://stderr',
    "Usage: php get_opal_data.php <phase> <category>\n".
    "       where <phase> is the rank of the phase (1 is baseline, 2 is F1, etc)\n".
    "       and <category> must be one of the following: ".
    join( ', ', $possible_category_list )."\n",
    5
  );
  exit;
}

$phase = $argv[1];
$category = $argv[2];
$params = $category_list[$argv[2]];

// add the postfix to the datasource based on the phase input argument
if( 1 < $phase ) $params['datasource'] .= sprintf( '-f%d', $phase-1 );

// Download binary data from Opal
output( sprintf( 'Downloading %s data from Opal to %s', $category, $base_dir ) );
$download_count = 0;
$missing_count = 0;

$response = opal_send( array(
  'datasource' => $params['datasource'],
  'table' => $params['table'],
  'counts' => 'true'
) );

$object = json_decode( $response );
$total = $object->valueSetCount;
$limit = 1000;
output( sprintf( 'There are %d rows', $total ) );

for( $offset = 0; $offset < $total; $offset += $limit )
{
  output( sprintf( 'Downloading files %d to %d', $offset, $offset + $limit - 1 ) );
  $response = opal_send( array(
    'datasource' => $params['datasource'],
    'table' => $params['table'],
    'valueSets' => NULL,
    'limit' => $limit,
    'offset' => $offset
  ) );

  $object = json_decode( $response );
  foreach( $object->valueSets as $value_set )
  {
    $uid = $value_set->identifier;

    // if the variable name starts with "Measure." then this is a repeated variable (download one at a time)
    if( preg_match( '/Measure\./', $params['variable'] ) )
    {
      $response = opal_send( array(
        'datasource' => $params['datasource'],
        'table' => $params['table'],
        'valueSet' => $uid,
        'variable' => $params['variable']
      ) );

      if( !$response )
      {
        $missing_count++;
        continue;
      }

      $object = json_decode( $response );
      if( !property_exists( $object, 'values' ) )
      {
        $missing_count++;
        continue;
      }

      foreach( $object->values as $value )
      {
        // determine the opal paramters from the link
        $opal_params = array();
        $key = NULL;
        $index = NULL;
        foreach( explode( '/', $value->link ) as $part )
        {
          // ignore empty strings
          if( 0 == strlen( $part ) ) continue;

          if( in_array( $part, ['datasource', 'table', 'valueSet', 'variable'] ) )
          {
            $key = $part;
          }
          else if( preg_match( '/value\?pos=([0-9]+)/', $part, $matches ) )
          {
            $index = $matches[1];
            $opal_params['value'] = NULL;
            $opal_params['pos'] = $index;
          }
          else
          {
            $opal_params[$key] = $part;
          }
        }

        // now download the data for this iteration
        $response = opal_send( $opal_params );

        if( $response )
        {
          $directory = sprintf(
            '%s/raw/clsa/%d/%s/%s',
            DATA_DIR,
            $phase,
            $params['name'],
            $uid
          );

          if( !is_dir( $directory ) ) mkdir( $directory );

          file_put_contents(
            sprintf( '%s/%s', $directory, preg_replace( '/<N>/', $index+1, $params['filename'] ) ),
            $response
          );
          $download_count++;
        }
        else
        {
          $missing_count++;
        }
      }
    }
    else
    {
      $response = opal_send( array(
        'datasource' => $params['datasource'],
        'table' => $params['table'],
        'valueSet' => $uid,
        'variable' => $params['variable'],
        'value' => NULL
      ) );

      if( $response )
      {
        $directory = sprintf(
          '%s/raw/clsa/%d/%s/%s',
          DATA_DIR,
          $phase,
          $params['name'],
          $uid
        );

        if( !is_dir( $directory ) ) mkdir( $directory );

        file_put_contents(
          sprintf( '%s/%s', $directory, $params['filename'] ),
          $response
        );
        $download_count++;
      }
      else
      {
        $missing_count++;
      }
    }
  }
}

output( sprintf(
  'Done, %d files downloaded, %d files not found',
  $download_count,
  $missing_count
) );

exit( 0 );
