<?php
set_time_limit( 60 ); // one minute
error_reporting( E_ALL | E_STRICT );
define( 'VERSION', '1.0' );

function out( $message )
{
  if( !DEBUG ) printf( "%s\n", $message );
}

function anonymize( $filename )
{
  $dicom_tag = array(
    '0008,1010' => '',      // Station Name
    '0010,0010' => '',      // Patient Name
    '0010,0020' => '',      // Patient ID
    '0010,1000' => '',      // Other Patient IDs
    '0008,0080' => 'CLSA',  // Instituion Name
    '0018,1000' => '',      // Device Serial Number
    '0019,1000' => NULL,    // Unknown Tag & Data
    '0023,1000' => NULL,    // Unknown Tag & Data
    '0023,1001' => NULL,    // Unknown Tag & Data
    '0023,1002' => NULL,    // Unknown Tag & Data
    '0023,1003' => NULL,    // Unknown Tag & Data
    '0023,1004' => NULL,    // Unknown Tag & Data
    '0023,1005' => NULL,    // Unknown Tag & Data
  );

  $modify_list = [];
  foreach( $dicom_tag as $key => $value )
  {
    $modify_list[] = sprintf(
      '-m "(%s)%s"',
      $key,
      is_null( $value ) ? '' : sprintf( '=%s', $value )
    );
  }

  $command = sprintf(
    'dcmodify -nb %s %s',
    implode( ' ', $modify_list ),
    FILENAME
  );

  $result_code = 0;
  $output = NULL;
  if( DEBUG )
  {
    printf( "%s\n", $command );
  }
  else
  {
    exec( $command, $output, $result_code );
  }

  if( 0 < $result_code )
  {
    printf( $output );
  }
}

function usage()
{
  printf(
    "anonymize_dxa_lateral.php version %s\n".
    "Usage: php anonymize_dxa_lateral.php [OPTION] [FILENAME]\n".
    "-d  Outputs the script's command without executing them\n".
    "-h  Displays this usage message\n",
    VERSION
  );
}

function parse_arguments( $arguments )
{
  $filename = NULL;
  $operation_list = [];
  foreach( $arguments as $index => $arg )
  {
    if( 0 == $index ) continue; // ignore the script name
    if( '-' == $arg[0] )
    {
      $option = substr( $arg, 1 );
      // check that the option is valid
      if( !in_array( $option, ['d', 'h'] ) )
      {
        printf( 'Invalid operation "%s"%s', $arg, "\n\n" );
        usage();
        die();
      }

      // add a new option
      $operation_list[] = [ 'option' => $option ];
    }
    else
    {
      // add an argument to the new option
      $arg = trim( $arg, "\"' \t" );
      $operation_index = count( $operation_list )-1;
      if(
        -1 == $operation_index ||
        array_key_exists( 'argument', $operation_list[$operation_index] ) ||
        in_array( $operation_list[$operation_index]['option'], ['d', 'h'] )
      ){
        // a single lone argument is allowed, the filename
        if( !is_null( $filename ) )
        {
          printf( 'Unexpected argument "%s"%s', $arg, "\n\n" );
          usage();
          die();
        }

        $filename = $arg;
      }
      else
      {
        $operation_list[$operation_index]['argument'] = $arg;
      }
    }
  }

  $settings = [
    'DEBUG' => false,
    'FILENAME' => $filename
  ];
  foreach( $operation_list as $op )
  {
    $option = $op['option'];
    $argument = array_key_exists( 'argument', $op ) ? $op['argument'] : NULL;

    if( 'd' == $option )
    {
      $settings['DEBUG'] = true;
    }
    else if( 'h' == $option )
    {
      usage();
      die();
    }
  }

  // make sure an input filename was provided
  if( is_null( $settings['FILENAME'] ) )
  {
    printf( "No input filename specified\n\n" );
    usage();
    die();
  }

  return $settings;
}

// parse the input arguments
foreach( parse_arguments( $argv ) as $setting => $value ) define( $setting, $value );

out( sprintf( 'Anonymizing file "%s"', FILENAME ) );
anonymize( FILENAME );
