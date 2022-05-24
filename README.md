# DownloadGedcomWithURL
A [weebtrees](https://webtrees.net) 2.1 custom module to download GEDCOM files on URL requests with the tree name, GEDCOM file name and authorization provided as parameters within the URL.

**Example URL:**  
http://MY_URL/webtrees/index.php?route=/webtrees/DownloadGedcomWithURL/tree/MY_TREE/filename/MY_FILENAME/privacy/MY_PRIVACY_LEVEL

MY_TREE specifies the webtrees tree name

MY_FILENAME has to be provided without .ged extension, i.e. use this_file instead of this_file.ged

For MY_PRIVACY_LEVEL, the following values can be used:
* gedadmin
* user 
* visitor  
* none     (Default)

**Example Script:**  
The file ExamplePythonScript.py contains an example, how an automatic download could be performed with a Python script

**IMPORTANT SECURITY NOTE:**  
Please note that installing this module will enable everyone, who can reach the webtrees URL, to download the GEDCOM files from webtrees. Therefore, you should consider to use this module in secure private networks only or apply addition access restrictions, e.g. for certain IP addresses only.

The module was developed and tested with [webtrees 2.1.4](https://webtrees.net/download)
