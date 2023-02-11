[![Latest Release](https://img.shields.io/github/v/release/Jefferson49/DownloadGedcomWithURL?display_name=tag)](https://github.com/Jefferson49/DownloadGedcomWithURL/releases/latest)
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.1.x-green)](https://webtrees.net/download)

## DownloadGedcomWithURL
A [weebtrees](https://webtrees.net) 2.1 custom module to download GEDCOM files on URL requests with the tree name, GEDCOM file name, and authorization provided as parameters within the URL.

## What are the benefits of this module?
+ Gedcom files can be automatically downloaded without logging into the user interface (webtrees front end)
+ Gedcom files can be downloaded with a script, see attached example scripts (in Python)
+ Gedcom files can be automatically saved to a folder on the webtrees server without logging into the user interface (webtrees front end)
+ Gedcom file backups on the server can be scheduled by a Cron Job on the server, see attached example script

## IMPORTANT SECURITY NOTE  
Please note for versions below v3.0.0 that installing the module enables everyone, who can reach the webtrees URL, to download the GEDCOM files from webtrees. Therefore, you should consider to use this module in secure private networks only or apply additional access restrictions, e.g. for certain IP addresses only.

Module versions starting from v3.0.0 use an access key, which is stored in the module preferences in webtrees. Access to the download is only allowed if the provided key in the URL is identical to a secret key in the webtrees database (settings). 

<span style="color:red">Please note that everyone with access to the secret key, can download GEDCOM files from your webtrees installation.</span>

## Installation
+ Download the [latest release](https://github.com/Jefferson49/DownloadGedcomWithURL/releases/latest) of the module
+ Copy the folder "download_gedcom_with_url" into the "module_v4" folder of your webtrees installation
+ Check if the module is activated in the control panel:
    + Login to webtrees as an administrator
	+ Go to "Control Panel/All Modules", and find the module called "DownloadGedcomWithURL"
	+ Check if it has a tick for "Enabled"
+ Provide a secret key in the module settings, see chapter below.
+ Specify in the module settings whether Gedcom files are allowed to be downloaded or not.

## Webtrees Version
The module was developed and tested with [webtrees 2.1.16](https://webtrees.net/download), but should also run with any other 2.1 version.

## Usage and API

### URL Format
http://MY_URL/webtrees/index.php?route=/webtrees/DownloadGedcomWithURL&tree=MY_TREE&key=MY_KEY&file=MY_FILENAME&privacy=MY_PRIVACY_LEVEL&format=MY_EXPORT_FORMAT&encoding=MY_ENCODING&line_endings=MY_ENDINGS&action=MY_ACTION&gedcom7=MY_GEDCOM7_FLAG&gedcom_l=MY_GEDCOM_L_FLAG

### Example URLs  
http://MY_URL/webtrees/index.php?route=/webtrees/DownloadGedcomWithURL&tree=tree1&key=hYHBiZM9

http://MY_URL/webtrees/index.php?route=/webtrees/DownloadGedcomWithURL&tree=tree1&key=hYHBiZM9&file=export

http://MY_URL/webtrees/index.php?route=/webtrees/DownloadGedcomWithURL&tree=tree1&key=hYHBiZM9&file=export&privacy=user&format=zip&encoding=ANSEL&line_endings=LF&gedcom7=1&gedcom_l=1

### URL Parameters  
* MY_TREE specifies the webtrees tree name
  * Mandatory parameter

* MY_KEY specifies a secure key, which restricts the access to the download
  * Mandatory parameter

* MY_FILENAME has to be provided without .ged extension, i.e. use this_file instead of this_file.ged
  * Tree name is taken as default if MY_FILENAME is not provided

* MY_PRIVACY_LEVEL specifies the user role, in which the GEDCOM export is executed
  * Valid values: gedadmin, user, visitor, none (Default)

* MY_EXPORT_FORMAT specifies the file format for the export
  * Valid values: gedcom (Default), zip, zipmedia, gedzip

* MY_ENCODING specifies the encoding of the generated GEDCOM file
  * Valid values: UTF-8 (Default), UTF-16BE, ANSEL, ASCII, CP1252

* MY_ENDINGS specifies the line endings in the generated GEDCOM file
  * Valid values: CRLF (Default), LF

* MY_ACTION specifies whether the Gedcom file will be downloaded, saved on the server, or both
  * Valid values: download (Default), save, both

* MY_GEDCOM7_FLAG specifies whether the generated GEDCOM file follows the GEDCOM 7 specification; default is GEDCOM 5.5.1
  * Valid values: 1
   
* MY_GEDCOM_L_FLAG specifies whether the GEDCOM-L standard shall be used additionally to GEDCOM 7
  * Valid values: 1

### Secret Key in the Module Settings
The key parameter of the URL is checked against a secret key. **The secret key is stored in the module settings**: Control Panel / Modules / All Modules / DownloadGedcomWithURL.

The provided secret key needs to have a minimum length of 8 characters.

**The control panel also provides an option for the key to be saved as an encrypted hash value**. This option is more secure, because the secret key is not visible to anyone and also encrypted in the database. However, the secret key is not readible any more (e.g. for other administrators) and cannot be recovered if it is forgotten.

### Example Script 
The file ExamplePythonScript.py contains an example, how an automatic download could be performed with a Python script

## Translation
You can help to translate this module. The translation is based on [gettext](https://en.wikipedia.org/wiki/Gettext) and uses .po files, which can be found in [/resources/lang/](https://github.com/Jefferson49/DownloadGedcomWithURL/tree/main/resources/lang). You can use a local editor like [Poedit](https://poedit.net/) or notepad++ to work on translations and provide them in the [Github repository](https://github.com/Jefferson49/DownloadGedcomWithURL) of the module. You can do this via a pull request (if you know how to do), or by opening a new issue and attaching a .po file. Updated translations will be included in the next release of this module.

Currently, the following languages are already available:
+ English
+ German

## Bugs and feature requests
If you experience any bugs or have a feature request for this webtrees custom module, you can [create a new issue](https://github.com/Jefferson49/DownloadGedcomWithURL/issues).

## Github Repository
https://github.com/Jefferson49/DownloadGedcomWithURL