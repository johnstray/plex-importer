<?php 
/**
 * RecursiveImport v1.0 - A script for mass processing of existing media
 * Copyright 2017 (c) John Stray & Joshua Smart - All Rights Reserved
 */

$cliOptions = getopt( "d:l:vh", array( "directory", "label", "version", "help" ) );

# Global Variables
$enableDebug = TRUE;
$verboseDebug = FALSE;
$loggingDir = "T:\\Temp\\Logs\\";
$debugLogArray = array();
$arrayOfDirectories = array();

function recursiveImport_main() {
	
	GLOBAL $cliOptions, $arrayOfDirectories;
	
  debugLog( "Checking CLI arguments to make sure we have the info we need..." );
	
  # Make sure a valid directory was passed into this script
  debugLog( "ARGV[1]: " . $cliOptions['d'] );
	$prompt = prompt( "\n\n\nPlease specify the base directory you wish to process [" . $cliOptions['d'] . "] :  " );
	if ( !empty( $prompt ) ) { $cliOptions['d'] = $prompt; }
  print( "\n\n\n" );
  debugLog( "Processing base directory: " . $cliOptions['d'] );
	if ( isset( $cliOptions['d'] ) ) {
		if ( is_dir( $cliOptions['d'] ) ) {
			if ( is_readable( $cliOptions['d'] ) ) {
				debugLog( "Everything appears to be OK with the directory path given. Continuing...\n", TRUE );
			} else {
				debugLog( "The specified directory is not readable!", TRUE );
        return false;
			}
		} else {
			debugLog( "The passed in path is not a valid directory!", TRUE );
      return false;
		}
	} else {
		debugLog( "No path was given to this script. Please make sure you use the -d parameter!", TRUE );
    return false;
	}
  
  # Checking for a valid label
  debugLog( "ARGV[2]: " . $cliOptions['l'] );
  $labelPrompt = prompt("\n\n\nWhat label should be used to determine how to process these files? [" . $cliOptions['l'] . "] : " );
  if ( !empty( $labelPrompt ) ) { $cliOptions['l'] = $labelPrompt; }
  debugLog( "Processing with label: " . $cliOptions['l'] );
  if ( empty( $cliOptions['l'] ) ) {
    debugLog( "ERROR: No label was given! You need to provide a label for us to process with.", TRUE );
    return false;
  } elseif ( in_array( $cliOptions['l'], $validLabels) === false ) {
    debugLog( "ERROR: The given label is not in the list of valid labels.", TRUE );
    return false;
  }

	# Get the list of directories that we will process
	print( "\n\n\n" );
  
  debugLog( "Scanning for directories...", TRUE );
	recursiveSearch( $cliOptions['d'] );
	$count = count( $arrayOfDirectories );
	debugLog( "Found ". $count . " directories to process!", TRUE );
  
  print("\n\n\n");
	
	# Loop over each of the directories and call the processor on each one.
	if ( count( $arrayOfDirectories ) > 0 ) {
		foreach ( $arrayOfDirectories as $directory ) {
			debugLog( "Processing directory: ".$directory );
      print("Processing directory: ".$directory."\r");
			exec("start php \"C:\\Tools\\plex-import\\plex-import.php\" -d \"".$directory."\" -n \"RecursiveImporter\" -l \"TVShows\"");
		  print_s("Processing directory: ".$directory,0);
    }
		return true;
	} else {
		debugLog( "The array of directories to process is empty. There is nothing to do here.", TRUE );
		return false;
	}

}

function recursiveSearch( $directory ) {
	
	GLOBAL $arrayOfDirectories;
	
	$items = glob( $directory . "\*", GLOB_ONLYDIR );
	if( count( $items ) > 0 ) {
		foreach( $items as $item ) {
			if ( count( glob( $item . "\\*", GLOB_ONLYDIR ) ) > 0 ) {
				debugLog( "Recursing into directory: " . $item );
        recursiveSearch($item);
			} else {
        debugLog( "Found: " . $item );
				$arrayOfDirectories[] = $item;
			}
		}
	} else {
		debugLog( "Nothing was found by scandir() when searching the given directory!" );
	}
	
}

/**
 * DebugLog
 * Adds an entry to the debugging log when debugging is enabled.
 */
function debugLog( $entry, $printDebug = FALSE ) {
  
  GLOBAL $debugLogArray, $enableDebug, $verboseDebug;
  
  if ( $enableDebug ) {
    $debugLogArray[] = date( 'Y-m-d H:i:s : ' ) . $entry;
	  if ( $verboseDebug || $printDebug ) { print( "DEBUGGING: " . $entry . "\n" ); }
  }
}

/**
 * DumpDebugLog
 * Tries to dump the debug log to file or outputs to cli if we can't
 */
function dumpDebugLog( $logArray ) {
  
  GLOBAL $loggingDir;
  $logDir = $loggingDir . date( 'Y-m-d' ) . "\\";
  $logFile = $logDir . date( 'Y-m-d H.i.s' ) . ".log";
  $dump2out = false;
  
  if ( !file_exists( $logDir ) ) {
	  if ( !mkdir( $logDir, 0777, true ) ) {
		  // Failed to create the log dir.
		  print( "\n\nERROR: Unable to create the log directory." );
      dump2out = true;
	  }
  }
  
  if ( is_array( $logArray ) && !empty( $logArray ) ) {
    $output = "";
    foreach( $logArray as $line ) {
      $output .= $line . "\n";
    }
    $fp = @fopen( $logFile, 'wt' );
    if ( $fp === false ) {
      print( "\n\nERROR: Unable to get a handle on the log file." );
      $dump2out = true;
    } else {
      $fwrite = fwrite( $fp, $output );
      fclose( $fp );
      if ( $fwrite === false ) {
        print( "\n\nERROR: Unable to write to the log file." );
        $dump2out = true;
      }
    }
  }
  
  if ( $dump2out ) {
    $d2o_prompt = "";
    while ( !in_array( $d2o_prompt, array('Y','y','N','n') ) ) {
      $d2o_prompt = prompt( "Logging to file has failed. Would you like to output the debugLog to the command line? [Y/n]: " );
      if ( $d2o_prompt == "" ) { $d2o_prompt = "Y"; }
    }
    if ( $d2o_prompt == "y" || $d2o_prompt == "Y" ) {
      print( "Printing the debugLog to the command line..." ); sleep(2);
      print( "\n\n\n" . $output );
    }
  }
}

require_once('cli-functions.php');

if ( recursiveImport_main() ) {
  print("\n\n\n");
  debugLog( "Everything seems to have completed successfully! Exiting...", TRUE );
} else {
  print("\n\n\n");
  debugLog("Something seems to have gone wrong! Have a look above for any errors.", TRUE;);
  passthru( "pause" );
}
