# Changelog for PlexImporter

 + v1.3.0
   - Rewrote filterFilename() function again. This also removed the need for a tag filter.
 + v1.2.0
   - Rewrote the filterFilename function to better handle things.
   - Fixed instanceQueue numbering display when gretaer than 9.
   - Updated list of tag filters. Added: repack, web, skgtv, tbs
 + v1.1.2
   - Added minimum filesize detection. Allows to filter out small files like RARBG.com.mp4
   - Added filename filter bypass for movies so that they wouldn't be trated like tv shows.
 + v1.1.1
   - json_decode failed because the argument to return as array was not specified. Argument added.
   - Reindexed instanceQueue array after clearing item so that the json_encoded data won't include
     the array keys. This was causing incorrect queue orders, because array key "0" didn't exist.
 + v1.1.0
   - Completely rewrote the instanceQueue functions. They now also use a JSON data file and array.
   - Modified CLI argument handling using the getopts() method instead of argv[];
   - Added preventCleanup=true when CLI argument checking fails.
   - Logfile now gets a datestamp when torrent name is not specified.
   - Save dir now always had the DS appended to it because it should never be provided on the 
     CLI as this would escape the closing double quotes causing remaining args to be a part of it.
     The directory option should always be enclosed in double quotes and never have trailing slash.
   - Removed "Scanning directory for suitable files..." notice on CLI output.
   - Reduced default execution delay to 15 seconds.
 + v1.0.3
   - Added creation of dated folders in the logging directory
 + v1.0.2
   - Modified CLI outputs: Removed extra \n from filename output. Added \n to "Processing Files"
 + v1.0.1
   - Added a sleep(30) at the begining to delay the script execution. Required because the script
     couldn't get a lock on the file as BitTorrent hadn't finished flushing to disk yet.
 + v1.0.0
   - Initial release
 
 # Changelog for RecursiveImporter
 + v1.0.0 (15 October 2017)
   - Initial Release
 
 # Changelog for CLI Functions
 + v1.0.0
   - Initial Release
