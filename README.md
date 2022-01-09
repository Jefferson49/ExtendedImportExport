# DownloadGedcomWithURL
A [weebtrees](https://webtrees.net) 2.0 middleware module to download GEDCOM files with URL requests with the GEDCOM file name and authorization provided as URL parameters.

**Example URL:**  
http://SOME_URL/webtrees/index.php?route=SOME_ROUTE&downloadgedcom=FILENAME&accesslevel=ACCESS_LEVEL

FILENAME has to be provided without .ged extension, i.e. use my_file instead of my_file.ged

For ACCESS_LEVEL, the following values can be used:
* gedadmin
* user 
* visitor  
*	none     (Default)

**Note:** The Gedcom file will always be downloaded from the last tree, which was used in the frontend.

**IMPORTANT SECURITY NOTE:**  
Please note that installing this module will enable everyone, who can reach the webtrees URLs, to download the GEDCOM files from webtrees. This even works if no user is logged in. Therefore, you should only consider to use this module in secure private networks etc.

The module was developed and tested with [webtrees 2.0.19](https://webtrees.net/download)
