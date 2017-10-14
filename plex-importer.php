<?php include('version.php');require("clifunc.php");
/**
 * Plex Importer v3.3.0 - A BitTorrent to Plex Media Server Bridge
 * Copyright 2014-2017 (c) John Stray - All Rights Reserved
 */

# Setup some variables
$btSaveDir      = (isset($cliOptions['d'])) ? $cliOptions['d'].DIRECTORY_SEPARATOR : '';  # Directory where Torrent Download is saved
$btTorrentName  = (isset($cliOptions['n'])) ? $cliOptions['n'] : '';  # The name of the torrent we're processing
$btTorrentLabel = (isset($cliOptions['l'])) ? $cliOptions['l'] : '';  # The torrent's label

switch ($btTorrentLabel) {
  case "TVShows": $btBaseDir = "P:\\BitTorrent\\TV Shows\\"; break;
  case "Movies": $btBaseDir = "P:\\BitTorrent\\Movies\\"; break;
  case "Anime": $btBaseDir = "P:\\BitTorrent\\Anime\\"; break;
  case "KidsTV": $btBaseDir = "P:\\BitTorrent\\KidsTV\\"; break;
  default: $btBaseDir = "P:\\BitTorrent\\"; break;
}

$destinations = array (
	"TVShows" => "T:\\\\",
	"Movies" => "M:\\New",
	"Anime" => "L:\\Anime",
	"KidsTV" => "L:\\KidsZone"
);

$MetaXTagDir    = "T:\\Temp\\MetaX";
$MetaX          = "C:\\Program Files (x86)\\MetaX\\MetaX.exe";

$ffmpeg         = "C:\\Media\\Apps\\ffmpeg\\bin\\ffmpeg.exe";
$ffprobe        = "C:\\Media\\Apps\\ffmpeg\\bin\\ffprobe.exe";

$tempDir        = "T:\\Temp\\";
$loggingDir     = "C:\\Media\\Logs\\";
$minFilesize    = 62914560; // 60 Mb

$nameFilter     = array( # Array of changes to make to filename - Processed top to bottom
  # Before                        # After
  'Scandal.US'                    =>  'Scandal.2012',                                               // Scandal (2012)
  'S.H.I.E.L.D'                   =>  'SHIELD',                                                     // Marvel's Agents of SHIELD
  'P.D'                           =>  'PD',                                                         // Chicago PD
  'Wheeler.Dealers.2013'          =>  'Wheeler.Dealers' ,                                           // Wheeler Dealers
  'Teenage.Mutant.Ninja.Turtles'  =>  'Teenage.Mutant.Ninja.Turtles.2012',                          // Teenage Mutant Ninja Turtles (2012)
  'Whose.Line.is.it.Anyway.US'    =>  'Whose.Line.is.it.Anyway',                                    // Whose Line is it Anyway?
  'The.Grand.Tour'                =>  'The.Grand.Tour.2016',                                        // The Grand Tour (2016)
  'Taboo.UK'                      =>  'Taboo.2017',                                                 // Taboo (2017)
  'Taboo'                         =>  'Taboo.2017',                                                 // Taboo (2017)
  'Outsiders.2016'                =>  'Outsiders',                                                  // Outsiders (2016)
  'Time.After.Time.US'            =>  'Time.After.Time.2017',                                       // Time After Time (2017)
  'Part.'                         =>  'S01E',                                                       // *Workaround for Big Little Lies
  'Guardians.of.the.Galaxy'		    =>  'Marvels.Guardians.of.the.Galaxy',							// Marvel's Guardians of the Galaxy
  'The.Powerpuff.Girls'			      =>  'The.Powerpuff.Girls.2016',									// The Powerpuff Girls (2016)
  'Teen.Titans.Go'				        =>  'Teen.Titans.Go!',											// Teen Titans GO!
);

$validLabels    = array( # Array of valid labels expected from BitTorrent
  'TVShows', 'Movies', 'Anime', 'KidsTV',
);

$validExts      = array( # Array of file extensions this script should handle
  'mp4', 'm4v', 'mkv', 'avi',
);

$globalError    = false;  # Error conditions will set this to true so we know on exit
$enableDebug    = true;   # Enable debug logging? Saves log to $tempDir
$preventCleanup = false;  # Prevents cleanup if triggered to allow for inspection after errors.
$debugLogArray  = array();# Create empty array for the debugLog to build into

function main() {
  
  GLOBAL $btSaveDir, $btBaseDir, $btTorrentName, $btTorrentLabel, $validLabels, $MetaXTagDir, $preventCleanup, $globalError, $tempDir;
  
	$error=false;
  
  # Print the header
  print_a();
  print_c("Plex Importer v3.3.0 - A BitTorrent to Plex Media Server Bridge",'center',"\n");
  print_c("Copyright 2014-2017 (c) John Stray - All Rights Reserved",'center',"\n");
  print_a();
  
	# CLI Argument Checking
  debugLog("Checking CLI arguments to make sure we have the info we need...");
  debugLog("ARGV[1]: ".$btSaveDir);
  if (empty($btSaveDir)) {
    print("\nERROR: Search directory not specified!");$error=true;
    debugLog("Search directory not specified! - Error condition triggered");
  } elseif (!is_dir($btSaveDir)) {
    print("\nERROR: Argument 1 is not a valid directory!");$error=true;
    debugLog("The search directory specified is not valid! - Error condition triggered");
  } else {debugLog("Search directory specified and valid - OK");}
  debugLog("ARGV[2]: ".$btTorrentName);
  if (empty($btTorrentName)) {
    print("\nERROR: Torrent name not specified!");$error=true;
    debugLog("A torrent name was not specified! - Error condition triggered");
  } else {debugLog("Torrent Name specified and valid - OK");}
  debugLog("ARGV[3]: ".$btTorrentLabel);
  if (empty($btTorrentLabel)) {
    print("\nWARNING: Torrent label not specified! Rejecting torrent!");$error=true;
    debugLog("A torrent label was not specified! - Error condition triggered");
    debugLog("Rejecting torrent because its label does not match any handled by this script");
  } elseif (in_array($btTorrentLabel, $validLabels) === false) {
    print("\nWARNING: Incorrect torrent label specified! I cannot handle this one.");$error=true;
    debugLog("The torrent label specified is not in the array of valid labels - Error condition triggered");
  } else {debugLog("Torrent label specified and valid - OK");}
    
  if ($error) {
    debugLog("An error condition was triggered whilst examining CLI arguments. Calling main_end() to quit.");
    $globalError = true; $preventCleanup = true;
    main_end();
  }
  
  sleep(15);
  
  # Check if we need to wait for another instance - This will handle waiting if we do
  $qpos = getQueuePosition($btTorrentName);
  debugLog("Getting queue position - Position: ".$qpos);
  if($qpos != 1){
    debugLog("Not first in queue. Going to wait for other instances with instanceWait()");
    if(instanceWait() !== false) {
      debugLog("We are now first in queue. Lets continue...");
    } else {
      
    }
  } 
  
  # Make sure the TagDir is empty otherwise we may have type contamination problems.
  if(!empty(array_filter(glob($MetaXTagDir."*"), 'is_file'))) {
    // Tag dir is not empty. Hold up until it is.
    debugLog("The MetaX processing directory is not empty, but I have not learned how to handle this yet. Continuing...");
  }
  
  # Get the list of file(s) to work on
  if(!($files=listValidFiles($btSaveDir))) {
    print("\nThere were no files found in the directory that are suitable to process.");
    debugLog("There were no files found in the directory that are suitable to process.");
    $preventCleanup = true; $globalError = true;
    main_end();
  }
  
  print("\n\nProcessing files...\n");
  foreach ($files as $sourceFile) {
    $bnsf = basename($sourceFile); // Debugging
    print($bnsf."\r");
    if(is_file($sourceFile) && is_readable($sourceFile)) {
      
      # Filter the filename
      $newFilename = filterFilename(basename($sourceFile));
      debugLog("[$bnsf] The new name for this file is: $newFilename");
			
			# Rename the file
			$rnfn = pathinfo($newFilename,PATHINFO_FILENAME).".".pathinfo($sourceFile,PATHINFO_EXTENSION);
			$renamedFile = $tempDir.$rnfn;
			if (rename($sourceFile, $renamedFile)) {
				debugLog("[$bnsf] File successfully renamed");
      
				# Get the codecs of the source file
				$codecArray=probeCodecs($renamedFile);
				if($codecArray !== false) {
					// Codecs obtained - dump to debuglog.
					
					# Tell FFMPEG to do its thing... This will also move the file for us.
					if(!convertCopy($renamedFile, $MetaXTagDir."//".$newFilename, $codecArray)){
						debugLog("[$bnsf] FFMpeg failed to convert the file");
						$error=true;
					} else {
						debugLog("[$bnsf] FFMpeg converted successfully. The resulting file should now be in the Tagging directory");
						// @TODO: Ditch the file in the temp dir
						if(unlink($renamedFile)) {
							debugLog("Removed the temporary pre-conversion file - OK");
						} else {
							debugLog("Failed to remove the temporary pre-conversion file. It will need to be removed manually");
						}
					}
				} else {
					debugLog("[$bnsf] FFProbe was not able to obtain codec information from the file");
					$error=true;
				}
			} else {
				debugLog("[$bnsf] Failed to rename the file to the new name");
				$error=true;
			}
    } else {
      debugLog("[$bnsf] The file could not be read or is not a file");
      $error=true;
    }
    if($error) {
      print_s($bnsf, 3);
      debugLog("[$bnsf] Error(s) occured whilst processing this file. It has been skipped.");
      $preventCleanup = true; // Prevents cleanup operation from running.
      $error=false; // Reset for next iteration
      continue;
    } else {
      print_s($bnsf, 0);
    }
  }
  
  # Call MetaX so that it can tag all the files with Metadata and take over from there.
  print("\n\nCalling MetaX to autotag the files with metadata...\r");
	debugLog("Calling MetaX to autotag the processed files with metadata");
  if(tagMetadata($MetaXTagDir, $btTorrentLabel)){
    print_s("Calling MetaX to autotag the files with metadata...",0);
		debugLog("Successfully tagged files with metadata");
    main_end();
  } else {
    print_s("Calling MetaX to autotag the files with metadata...",1);
		debugLog("MetaX quit with an error condition. This likely means that files have not been tagged");
    $preventCleanup = true; $globalError = true;
    main_end();
  }
  
}

/**
 * InstanceWait
 * Check for other instances of this script and wait untill they are complete before continuing
 */
function instanceWait() {
  
  GLOBAL $btTorrentName;
  $queuePos = 0;
  
  print("\n\nAnother instance is already running!\r");
  
  while($queuePos !== 1) {
    $queuePos = getQueuePosition($btTorrentName);
    if($queuePos === false){
      print("Failed to get a position in the instance queue!\n");
      return false;
    } else {
      sleep(5);
      print("Another instance is already running! Queue Position: ".$queuePos."  \r");
    }
  }
  
  print_c(" ",'center',"\r");
  
}

/**
 * GetQueuePosition
 * Gets the queue position of this instance or creates entry if not found in queue.
 * Returns the position in queue as integer or false if something went wrong.
 */
function getQueuePosition($queueItem) {
  
  GLOBAL $tempDir;
  $instanceFile = $tempDir . "instanceQueue.json";
  $queuePosition = false;
  
  while($queuePosition === false) {  
    if (file_exists($instanceFile)) {
      if (!is_readable($instanceFile)) {
        # Break (return false) occurs here if the file is not readable.
        debugLog("Failed to get instanceQueue position. The file is not readable"); break;
      } else {
        for($i=0; $i<5; $i++) {
          $instanceItemsJson = file_get_contents($instanceFile);
          if($instanceItemsJson === false) {
            $fileGet = false; sleep(2); continue;} else {$fileGet = true; break;
          }
        } if (!$fileGet) {
          # Break (return false) occurs here if we couldn't read the file.
          debugLog("Failed to get instanceQueue position. The file could not be read"); break;
        } else {
          $instanceQueueArray = json_decode($instanceItemsJson, true);
          if(!is_array($instanceQueueArray)) {
            # Break (return false) occurs here if the JSON data could not be decoded correctly.
            debugLog("Failed to get instanceQueue position. The JSON data could not be decoded"); break;
          } else {
            if (in_array($queueItem, $instanceQueueArray)) {
              $queuePosition = array_search($queueItem, $instanceQueueArray) + 1;
              break;
            } else {
              $instanceQueueArray[] = $queueItem;
              $instanceQueueArray = array_values($instanceQueueArray); // Just to be sure...
              $instanceItemsJson = json_encode($instanceQueueArray);
              for($i=0; $i<5; $i++) {
                $filePut = file_put_contents($instanceFile, $instanceItemsJson, LOCK_EX);
                if ($filePut === false) {sleep(2); continue;} else {break;}
              } if (!$filePut) {
                # Break (return false) occurs here if we couldn't write the appended JSON data back to file.
                debugLog("Failed to get instanceQueue position. Could not write appended JSON data to file"); break;
              } else {continue;}
            }
          }
        }
      }
    } else {
      for($i=0; $i<5; $i++) {
        if(touch($instanceFile)) {
          $fileTouch = true; break;} else {$fileTouch = false; sleep(2); continue;
          }
      } if (!$fileTouch) {
        # Break (return false) occurs here if we couldn't create the file.
        debugLog("Failed to get instanceQueue position. Could not create the queue file"); break;
      } else {
        $instanceQueueArray = array();
        $instanceItemsJson = json_encode($instanceQueueArray);
        for($i=0; $i<5; $i++) {
          $filePut = file_put_contents($instanceFile, $instanceItemsJson, LOCK_EX);
          if ($filePut === false) {sleep(2); continue;} else {break;}
        } if (!$filePut) {
          # Break (return false) occurs here if we couldn't write the appended JSON data back to file.
          debugLog("Failed to get instanceQueue position. Could not write appended JSON data to file"); break;
        } else {continue;}
      }
    }
  }
  
  return $queuePosition; # Returns false if something went wrong.
  
}

/**
 * ClearQueuePosition
 * Clears this instance from the queue, deleting the queue if there are no others.
 * Returns true if successfull or false if something went wrong.
 */
function clearQueuePosition($queueItem) {
  
  GLOBAL $tempDir;
  $instanceFile = $tempDir . "instanceQueue.json";
  
  if (file_exists($instanceFile)) {
    if (!is_writeable($instanceFile)) {
      debugLog("Failed to clear instanceQueue position. The queue file is not writeable??");
      return false;
    } else {
      for($i=0; $i<5; $i++) {
        $instanceItemsJson = file_get_contents($instanceFile);
        if($instanceItemsJson === false) {
          $fileGet = false; sleep(2); continue;} else {$fileGet = true; break;
        }
      } if (!$fileGet) {
        debugLog("Failed to clear instanceQueue position. The queue file could not be read");
        return false;
      } else {
        $instanceQueueArray = json_decode($instanceItemsJson,true);
        if (!is_array($instanceQueueArray)) {
          debugLog("Failed to clear instanceQueue position. The JSON data could not be decoded");
          return false;
        } else {
          $instanceQueueArray = array_diff($instanceQueueArray, array($queueItem));
          $instanceQueueArray = array_values($instanceQueueArray);
          if (empty($instanceQueueArray)) {
            if (unlink($instanceFile) === false) {
              debugLog("Failed to clear instanceQueue position. Could not unlink the queue file");
              return false;
            } else {return true;}
          } else {
            $instanceItemsJson = json_encode($instanceQueueArray);
            for($i=0; $i<5; $i++) {
              $filePut = file_put_contents($instanceFile, $instanceItemsJson, LOCK_EX);
              if ($filePut === false) {sleep(2); continue;} else {break;}
            } if (!$filePut) {
              debugLog("Failed to get instanceQueue position. Could not write appended JSON data to file");
              return false;
            } else {return true;}
          }
        }
      }
    }
  } else{
    debugLog("Failed to clear instanceQueue position. The queue file disappeared??");
    return false;
  }
  
}

/**
 * ListValidFiles
 * Get a list of files for the Torrent Save Directory that are suitable for this script to process.
 */
function listValidFiles($directory) {
  
  GLOBAL $validExts, $minFilesize;
  $files = null;
  
  debugLog("Scanning directory for suitable files...");
  
  if(is_dir($directory)) {
    if($handle = opendir($directory)) {
      $files = array();
      while(false !== ($file = readdir($handle))) {
        if(is_file($directory.$file)) {
					$pathParts = pathinfo($file);
					$fileExt = (array_key_exists('extension',$pathParts)) ? $pathParts['extension'] : "";
          if(in_array($fileExt, $validExts)) {
            if(filesize($directory.$file) > $minFilesize) {
              $files[] = $directory.$file;
              debugLog("Found suitable file: $file");
            } else {debugLog("Found a file but it was too small: $file");}
          } else {debugLog("Found a file but its not suitable: $file");}
        } else {debugLog("Found a directory: $file");}
      } closedir($handle);
    } else {debugLog("Failed to open directory for scanning!");}
  } else {debugLog("Specified direectory could not be identified as a directory!");}
  
  if(is_array($files) && count($files) > 0) {
    return $files;
  } else {
    debugLog("Scanning of directory for suitable files has failed!");
    return false;
  }
  
}

/**
 * FilterFilename
 * Filter the filename so that MetaX can process it properly.
 */
function filterFilename ($filename) {
	
	GLOBAL $nameFilter, $btTorrentLabel;
	$pathinfo = pathinfo($filename);
	
	# Filter filename against the replace array
	foreach($nameFilter as $before => $after) {
		$pathinfo['filename'] = str_ireplace($before,$after,$pathinfo['filename']);
	}
	
	# Add some sanity to the string - Deal with dots, dashes, etc.
	$pathinfo['filename'] = str_ireplace('.',' ',$pathinfo['filename']);
	$pathinfo['filename'] = ucwords($pathinfo['filename']);
	
	if($btTorrentLabel != "Movies") {
		# Find season and episode numbers
		preg_match("/S\d\dE\d\d/i", $pathinfo['filename'], $SEmatches, PREG_OFFSET_CAPTURE); // Find SxxExx
		preg_match("/\d{1,2}x\d\d/i", $pathinfo['filename'], $SXmatches, PREG_OFFSET_CAPTURE); // Find SSxEE
		preg_match_all("/\d{3,4}/i", $pathinfo['filename'], $SNmatches, PREG_OFFSET_CAPTURE); // Find SSEE
		
		if(!empty($SEmatches[0])) {
			$showname = trim(substr($pathinfo['filename'],0,$SEmatches[0][1]));
			$episode  = strtoupper($SEmatches[0][0]);
			preg_match("/\d\d\d\d/", $showname, $SEyear);
			if(!empty($SEyear)) {
				$showyear = "(".$SEyear[0].")";
				$showname = substr($showname,0,-4) . $showyear;
			}
		} elseif (!empty($SXmatches)) {
			$showname = trim(substr($pathinfo['filename'],0,$SXmatches[0][1]));
			$episode  = explode("x", $SXmatches[0][0]);
			$episode  = "S".str_pad($episode[0],2,"0",STR_PAD_LEFT)."E".str_pad($episode[1],2,"0",STR_PAD_LEFT);
			preg_match("/\d\d\d\d/", $showname, $SEyear);
			if(!empty($SEyear)) {
				$showyear = "(".$SEyear[0].")";
				$showname = substr($showname,0,-4) . $showyear;
			}
		} elseif (!empty($SNmatches)) {
			$SNmatches = array_slice($SNmatches[0],0,2);
			if(count($SNmatches) === 1) { // Only episode found
				$showname = trim(substr($pathinfo['filename'],0,$SNmatches[0][1]));
				$episode = str_pad($SNmatches[0][0],4,"0",STR_PAD_LEFT);
				$episode = "S" . substr($episode,0,-2) . "E" . substr($episode,-2,2);
				preg_match("/\d\d\d\d/", $showname, $SEyear);
				if(!empty($SEyear)) {
					$showyear = "(".$SEyear[0].")";
					$showname = substr($showname,0,-4) . $showyear;
				}
			} elseif(count($SNmatches) === 2) {
				if($SNmatches[1][0] == "264") {
					$showname = trim(substr($pathinfo['filename'],0,$SNmatches[0][1]));
					$episode = str_pad($SNmatches[0][0],4,"0",STR_PAD_LEFT);
					$episode = "S" . substr($episode,0,-2) . "E" . substr($episode,-2,2);
					preg_match("/\d\d\d\d/", $showname, $SEyear);
					if(!empty($SEyear)) {
						$showyear = "(".$SEyear[0].")";
						$showname = substr($showname,0,-4) . $showyear;
					}
				} else {
					$showname = trim(substr($pathinfo['filename'],0,$SNmatches[1][1]));
					$episode = str_pad($SNmatches[1][0],4,"0",STR_PAD_LEFT);
					$episode = "S" . substr($episode,0,-2) . "E" . substr($episode,-2,2);
					preg_match("/\d\d\d\d/", $showname, $SEyear);
					if(!empty($SEyear)) {
						$showyear = "(".$SEyear[0].")";
						$showname = substr($showname,0,-4) . $showyear;
					}
				}
			}
		}
		$pathinfo['filename'] = $showname . " " . $episode . ".mp4";
	}
	
	return $pathinfo['filename'];
	
}

/**
 * ProbeCodecs
 * Probe the file for codecs so we can tell FFMPEG what we want to do.
 * @TODO: Add error handling...!!!
 */
function probeCodecs($file) {
  
  GLOBAL $ffprobe;
  $video=$audio=$subtitle=false;
  
  debugLog("{FFPROBE} Identifying codecs in container file...");
  // @TODO: Add some error handling below - Maybe some info passthrough to the debuglog
  $ds = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w"));
  $ffprobe_po = proc_open($ffprobe.' -v error -print_format json -show_streams "'.$file.'"', $ds, $pipes);
  $stdout = stream_get_contents($pipes[1]);fclose($pipes[1]);
  $stderr = stream_get_contents($pipes[2]);fclose($pipes[2]);
  $parsed = json_decode($stdout, true);
  $ffprobe_rv = proc_close($ffprobe_po);
  
  foreach ($parsed["streams"] as $stream => $values) {
    if ($values["codec_type"] == "video" && !$video) {
      $codecs['video'] = array(
        'codec_name' => $values["codec_name"],
        'definition' => 'unknown',
        'frame_size' => $values["width"] . 'x' . $values["height"],
        # @TODO: Determine definition and print to debugLog. We may be able to tag the file with this.
      );
      $video = true;
      debugLog("{FFPROBE} Video codec found: ".$codecs['video']['codec_name']." ".$codecs['video']['definition']." ".$codecs['video']['frame_size']);
    } elseif ($values["codec_type"] == "audio" && !$audio) {
      $codecs['audio'] = array(
        'codec_name' => (!empty($values["codec_name"])) ? $values["codec_name"] : '',
        'channels'   => (!empty($values["channels"])) ? $values["channels"] : '',
        'bitrate'    => (!empty($values["bitrate"])) ? $values["bitrate"] : '',
      );
      $audio = true;
      debugLog("{FFPROBE} Audio codec found: ".$codecs['audio']['codec_name'].", ".$codecs['audio']['channels']." channels, ".$codecs['audio']['bitrate']."bps");
    } elseif ($values["codec_type"] == "subtitle" && !$subtitle) {
      if ($values["codec_tag_string"] == "text") {
        $codecs['subtitle'] = array(
          'codec_name' => $values["codec_name"],
        );
      }
      $subtitle = true;
      debugLog("{FFPROBE} Subtitle codec found: ".$codecs['subtitle']['codec_name']);
    }
  }
  
  return $codecs;
  
}

/**
 * ConvertCopy
 * Remux or Convert (as required) the streams from the original file to a new MP4 container file
 * Expects the following codec array:
   $codecsBefore = array(
     'video' => array(
       'codec_name' => (string) 'h264',
       'definition' => (string) '480p',
       'frame_size' => (string) '720x480'
     ),
     'audio' => array(
       'codec_name' => (string) "aac",
       'channels' => (int) 2,
       'bitrate' => (int) 128000
     ),
     'subtitle' => array(
       'codec_name' => (string) 'mov_text'
     )
   );
 */
function convertCopy($fromFile, $toFile, $codecsBefore) {
  
  GLOBAL $ffmpeg;
  $arguments = "";
  
  debugLog("{FFMPEG} Remuxing or converting streams into an MP4 container...");
  
  foreach ($codecsBefore as $type => $values) {
    switch ($type) {
      case 'video':
        if($values['codec_name'] != "h264") {
          $arguments .= " -vcodec libx264 -b:v 1024k";
          debugLog("{FFMPEG} Converting video stream to H.264");
        } else {
          $arguments .= " -vcodec copy";
          debugLog("{FFMPEG} Copying video stream without conversion");
        } break;
      case 'audio':
        if(in_array($values['codec_name'], array("m4a","aac","ac3")) === false) {
          if($values['channels'] <= 2) {
            $arguments .= " -acodec aac";
            $bitrate = (int) $values['channels'] * 64000;
            $bitrate = (string) $bitrate;
            $arguments .= " -b:a ".$bitrate;
            debugLog("{FFMPEG} Converting audio stream to AAC Stereo");
          } elseif($values['channels'] > 2) {
            $channels = (string) $values['channels'];
            $arguments .= " -acodec ac3 -ac ".$channels;
            $bitrate = (int) $values['channels'] * 64000;
            $bitrate = (string) $bitrate;
            $arguments .= " -b:a ".$bitrate;
            debugLog("{FFMPEG} Converting audio stream to AC3 Multi-channel");
          } else {
            # For some reason we couldn't handle the audio channels... Maybe we had 'null' channels?
            debugLog("{FFMPEG} Failed to convert audio stream! -  Couldn't determine channel quantity");
            return false;
          }
        } else {
          $arguments .= " -acodec copy";
          debugLog("{FFMPEG} Copying audio stream without conversion");
        } break;
      case 'subtitle':
        if($values['codec_name'] != "mov_text") {
          $arguments .= " -scodec mov_text";
          debugLog("{FFMPEG} Converting subtitles to TX3G (MOV Text)");
        } else {
          $arguments .= " -scodec copy";
          debugLog("{FFMPEG} Copying subtitles without conversion");
        } break;
    }
  }
  
  // @TODO: Add some error handling below - Maybe some stats output to the debug log?
  $arguments = '-loglevel info -i "'.$fromFile.'"'.$arguments.' "'.$toFile.'"';
	// Add this between the first ' and " to set affinity on the next round of running.
	// cmd /C start /wait "FFMPEG is processing..." /normal /affinity 1 
	exec('"'.$ffmpeg.'" '.$arguments, $fout, $result);
	if($result !== 0) {
	  if(is_array($fout)) {
         foreach ($fout as $value) {
		debugLog("{FFMPEG Error}: ".$value);
         }
       } else {
         debugLog("{FFMPEG Error}: ".$fout);
       }
     }
  return ($result === 0) ? true : false;
  
}

/**
 * TagMetadata
 * Call MetaX so that it can takeover and tag the file and add to the library.
 */
function tagMetadata($directory, $label) {
	
	GLOBAL $MetaX, $destinations;
  
  switch ($label) {
    case "TVShows":
      exec('"'.$MetaX.'" /T /C /A "'.$directory.'" /AT "'.$destinations["TVShows"].'"', $mxout, $mx);
      if($mx==0){return true;}else{return false;}
      break;
    case "Movies":
      exec('"'.$MetaX.'" /V /C /A "'.$directory.'" /AT "'.$destinations["Movies"].'"', $mxout, $mx);
      if($mx==0){return true;}else{return false;}
      break;
    case "Anime":
      exec('"'.$MetaX.'" /T /C /A "'.$directory.'" /AT "'.$destinations["Anime"].'"', $mxout, $mx);
      if($mx==0){return true;}else{return false;}
      break;
    case "KidsTV":
      exec('"'.$MetaX.'" /T /C /A "'.$directory.'" /AT "'.$destinations["KidsTV"].'"', $mxout, $mx);
      if($mx==0){return true;}else{return false;}
      break;
  }
}

/**
 * CleanupDirectory
 * Recursively remove junk files from the Torrent Save Directory then remove the directory itself.
 */
function cleanupDirectory($directory) {
  
  GLOBAL $btBaseDir;
  
  if($directory != $btBaseDir) {
    print("\n\nCleaning up...");
    debugLog("Cleaning up the input directory...");
    $items=scandir($directory);
    foreach($items as $item) {
      if($item=='.' || $item=='..'){continue;}
      $path = $directory.'/'.$item;
      if(is_dir($path)){
        $rrmdir = rrmdir($path);
        $dir=basename($path);
        if($rrmdir) {
          debugLog("Removed directory: $dir");
        } else {
          debugLog("Failed to remove directory: $dir");
        }
      } else {
        $unlink = unlink($path);
        $file=basename($path);
        if($unlink) {
          debugLog("Removed file: $file");
        } else {
          debugLog("Failed to remove file: $file");
        }
      }
    }
    $rmdir = rmdir($directory);
    $dir = basename($directory);
    if($rmdir) {
      debugLog("Removing base directory: $dir");
    } else {
      debugLog("Failed to remove base directory: $dir");
    }
  }
}

/**
 * MainEnd
 * Finish up after the script
 */
function main_end() {
  
  GLOBAL $enableDebug, $globalError, $preventCleanup, $debugLogArray, $btTorrentName, $btSaveDir;
  
  if(!$preventCleanup) {cleanupDirectory($btSaveDir);}
  dumpDebugLog($debugLogArray);
  clearQueuePosition($btTorrentName);
  
  if($globalError) {
    print("\n\n\nOne or more errors occured whilst processing so we have decided to quit.");
  }
  if($enableDebug && $globalError) {
    print("\nThe debug log will be able to give you more information about what went wrong.\n\n");
  }
  if($enableDebug && $globalError) {
    passthru("pause");
  }
  
  die("Exiting...");
  
}

/**
 * DebugLog
 * Adds an entry to the debugging log when debugging is enabled.
 */
function debugLog($entry) {
  
  GLOBAL $debugLogArray, $enableDebug;
  
  if($enableDebug) {
    $debugLogArray[] = date('Y-m-d H:i:s : ').$entry;
  }
}

/**
 * DumpDebugLog
 * Tries to dump the debug log to file or outputs to cli if we can't
 */
function dumpDebugLog($logArray) {
  
  GLOBAL $tempDir, $btTorrentName, $loggingDir;
  $logDir = $loggingDir.date('Y-m-d')."\\";
  if (empty($btTorrentName)) {
    $logFile = $logDir.date('Y-m-d H.i.s').".log";
  } else {
    $logFile = $logDir.$btTorrentName.".log";
  }
  $dump2out = false;
  
  if (!file_exists($logDir)) {
	  if (!mkdir($logDir, 0777, true)) {
		  // Failed to create the log dir.
      print("ERROR: Unable to create the log directory. Dumping to screen output.");
		  $dump2out = true;
	  }
  }
  
  if(is_array($logArray) && !empty($logArray)) {
    $output = "";
    foreach($logArray as $line) {
      $output .= $line."\n";
    }
    $fp = @fopen($logFile, 'wt');
    if($fp === false) {
      print("\nERROR: Unable to get a handle on the log file. Dumping to screen output.");
      $dump2out = true;
    } else {
      $fwrite = fwrite($fp, $output);
      fclose($fp);
      if($fwrite === false) {
        print("\nERROR: Unable to write to the log file. Dumping to screen output.");
        $dump2out = true;
      }
    }
  }
  
  if($dump2out) {print("\n\n\n".$output);}
  
}

main();


