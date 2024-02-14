<?php
/**
 * DATA_TYPE: actigraph
 * 
 * Actigraph data files recorded by wearables
 */

namespace data_type;

require_once( __DIR__.'/base.class.php' );

class actigraph extends base
{
  /**
   * Processes all actigraph files
   * 
   * @param string $identifier_name The name of the identifier used in actigraph filenames
   * @param string $study The name of the study that files come from
   */
  public static function process_files( $identifier_name, $study )
  {
    $base_dir = sprintf( '%s/%s', DATA_DIR, TEMPORARY_DIR );
    $study_uid_lookup = self::get_study_uid_lookup( $identifier_name, true, true ); // include event and consent data

    // Each site has their own directory, and in each site directory there are sub-directories for
    // each modality (actigraph, ticwatch, etc).  Within the actigraph directory there is one file
    // per participant named after the participant's study_id and the date of the data:
    // For example: "temporary/XXX/4/actigraph/<study_id> <date>.gt3x" (where 4 is the phase)
    output( sprintf( 'Processing actigraph files in "%s"', $base_dir ) );
    $file_count = 0;

    $glob = sprintf( '%s/[A-Z][A-Z][A-Z]/[0-9]/actigraph/*', $base_dir );
    foreach( glob( $glob ) as $filename )
    {
      $preg = '#/([0-9])/actigraph/([^/]+) \(([0-9]{4}-[0-9]{2}-[0-9]{2})\)(_thigh|_wrist)?\.gt3x$#';
      $matches = [];
      if( !preg_match( $preg, $filename, $matches ) )
      {
        $reason = sprintf(
          'Cannot transfer actigraph file, "%s", invalid format.',
          $filename
        );
        self::move_from_temporary_to_invalid( $filename, $reason );
        continue;
      }

      $phase = $matches[1];
      $study_id = strtoupper( trim( $matches[2] ) );
      $date = str_replace( '-', '', $matches[3] );
      $type = 5 <= count( $matches ) ? trim( $matches[4], '_' ) : 'unknown';

      if( !array_key_exists( $study_id, $study_uid_lookup ) )
      {
        $reason = sprintf(
          'Cannot transfer actigraph data due to missing UID lookup for study ID "%s"',
          $study_id
        );
        self::move_from_temporary_to_invalid( $filename, $reason );
        continue;
      }
      $uid = $study_uid_lookup[$study_id]['uid'];
      $sleep_consent = $study_uid_lookup[$study_id]['sleep_consent'];
      $mobility_consent = $study_uid_lookup[$study_id]['mobility_consent'];
      $home_date = $study_uid_lookup[$study_id]['home_date'];
      $site_date = $study_uid_lookup[$study_id]['site_date'];

      // if the thigh/wrist type wasn't in the filename, look in the file's data instead
      if( 'unknown' == $type )
      {
        $file = file_get_contents( $filename );
        $type = 'unknown';
        if( $file )
        {
          if( preg_match( '/"Limb":"Thigh"/', $file ) ) $type = 'thigh';
          else if( preg_match( '/"Limb":"Wrist"/', $file ) ) $type = 'wrist';
        }
      }

      if( 'wrist' == $type )
      {
        // make sure the participant has consented to sleep trackers
        if( !$sleep_consent )
        {
          $reason = sprintf(
            'Wrist actigraph data without sleep consent, "%s".',
            $filename
          );
          self::move_from_temporary_to_invalid( $filename, $reason );
          continue;
        }
      }
      else if( 'thigh' == $type )
      {
        // make sure the participant has consented to mobility trackers
        if( !$mobility_consent )
        {
          $reason = sprintf(
            'Thigh actigraph data without mobility consent, "%s".',
            $filename
          );
          self::move_from_temporary_to_invalid( $filename, $reason );
          continue;
        }
      }
      else
      {
        $reason = sprintf(
          'No limb defined in actigraph file, "%s".',
          $filename
        );
        self::move_from_temporary_to_invalid( $filename, $reason );
        continue;
      }

      // make sure the date aligns with the participant's events
      $date_object = new DateTime( $date );
      $diff = NULL;

      // the thigh is done after the home interview
      if( 'thigh' == $type && $home_date ) $diff = $date_object->diff( new DateTime( $home_date ) );
      // the wrist is done after the site interview
      else if( 'wrist' == $type && $site_date ) $diff = $date_object->diff( new DateTime( $site_date ) );

      // only allow up to two days before or after
      if( is_null( $diff ) || 2 < $diff->days )
      {
        $reason = sprintf(
          'Invalid date found in %s actigraph file, "%s".',
          $type,
          $filename
        );
        self::move_from_temporary_to_invalid( $filename, $reason );
        continue;
      }

      $destination_directory = sprintf(
        '%s/%s/%s/%s/actigraph/%s',
        DATA_DIR,
        RAW_DIR,
        $study,
        $phase,
        $uid
      );

      // make sure the directory exists (recursively)
      if( !TEST_ONLY && !is_dir( $destination_directory ) ) mkdir( $destination_directory, 0755, true );

      $destination = sprintf( '%s/%s_%s.gt3x', $destination_directory, $type, $date );
      $copy = TEST_ONLY ? true : copy( $filename, $destination );
      if( $copy )
      {
        if( VERBOSE ) output( sprintf( '"%s" => "%s"', $filename, $destination ) );
        if( !TEST_ONLY && !KEEP_FILES ) unlink( $filename );
        $file_count++;
      }
      else
      {
        $reason = sprintf(
          'Failed to copy "%s" to "%s"',
          $filename,
          $destination
        );
        self::move_from_temporary_to_invalid( $filename, $reason );
      }
    }

    output( sprintf(
      'Done, %d files %stransferred',
      $file_count,
      TEST_ONLY ? 'would be ' : ''
    ) );
  }
}
