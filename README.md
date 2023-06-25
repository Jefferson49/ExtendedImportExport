[![Latest Release](https://img.shields.io/github/v/release/Jefferson49/DownloadGedcomWithURL?display_name=tag)](https://github.com/Jefferson49/DownloadGedcomWithURL/releases/latest)
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.1.x-green)](https://webtrees.net/download)

## DownloadGedcomWithURL
A [webtrees](https://webtrees.net) 2.1 custom module to download or store GEDCOM files on URL requests with the tree name, GEDCOM file name, and authorization provided as parameters within the URL.

## What are the benefits of this module?
+ Gedcom files can be automatically downloaded without logging into the user interface (webtrees front end)
+ Gedcom files can be downloaded with a script, see attached example scripts
+ Gedcom files can be automatically saved to a folder on the webtrees server without logging into the user interface (webtrees front end)
+ Gedcom file backups on the server can be scheduled by a Cron Job on the server, see attached example script
+ Gedcom files can be downloaded/stored in the Gedcom 7 format (beta version)

## IMPORTANT SECURITY NOTE  
**Module versions starting from v3.0.0 use an authorization key**, which is stored in the module preferences in webtrees. Access to the download is only allowed if the provided key in the URL is identical to a authorization key in the webtrees database (settings). 

**Please note that everyone with access to the authorization key, can download GEDCOM files from your webtrees installation.**

Please note that the earlier **versions v1.0.0 and v2.0.0 did NOT use an authorization key**. This means that installing this module versions enables everyone, who can reach the webtrees URL, to download the GEDCOM files from webtrees. Therefore, you should consider to use this module versions in secure private networks only or apply additional access restrictions, e.g. for certain IP addresses only.

## Installation
+ Download the [latest release](https://github.com/Jefferson49/DownloadGedcomWithURL/releases/latest) of the module
+ Copy the folder "download_gedcom_with_url" into the "module_v4" folder of your webtrees installation
+ Check if the module is activated in the control panel:
    + Login to webtrees as an administrator
	+ Go to "Control Panel/All Modules", and find the module called "DownloadGedcomWithURL"
	+ Check if it has a tick for "Enabled"
+ Provide an authorization key in the module settings, see chapter below
+ Specify in the module settings whether Gedcom files are allowed to be downloaded or not
+ Optionally, specify default settings in the control panel, which might reduce the number of parameters provided within the URL
+ Optionally, test the download setting with the "Test Download" button in the control panel

## Webtrees Version
The module was developed and tested with [webtrees 2.1.16](https://webtrees.net/download), but should also run with any other 2.1 version.

## Usage and API

### URL Format
The full URL format, which contains all possible parameters is defined as follows:

http://MY_URL/webtrees/index.php?route=/webtrees/DownloadGedcomWithURL&tree=MY_TREE&key=MY_KEY&file=MY_FILENAME&privacy=MY_PRIVACY_LEVEL&format=MY_EXPORT_FORMAT&encoding=MY_ENCODING&line_endings=MY_ENDINGS&action=MY_ACTION&time_stamp=MY_TIME_STAMP&gedcom7=MY_GEDCOM7_FLAG&gedcom_l=MY_GEDCOM_L_FLAG

The "MY_xxx" place holders need to be replaced by the specific parameter values, which shall be used for the download. The possible values for the URL parameters are described below.

It is not mandatory to provide all parameters. The only mandatory parameter is the authorization key. If any of the other parameters is not provided, the default value, which is specified in the control panel is used.

### Example URLs  
http://MY_URL/webtrees/index.php?route=/webtrees/DownloadGedcomWithURL&key=hYHBiZM9

http://MY_URL/webtrees/index.php?route=/webtrees/DownloadGedcomWithURL&key=hYHBiZM9&tree=tree1&file=export

http://MY_URL/webtrees/index.php?route=/webtrees/DownloadGedcomWithURL&key=hYHBiZM9&tree=tree1&file=export&privacy=user&format=zip&encoding=ANSEL&line_endings=LF&action=both&time_stamp=prefix&gedcom7=1&gedcom_l=1

### Values for URL Parameters  
* MY_KEY specifies a authorization key, which restricts the access to the download
  * This is the only mandatory parameter. If it is not provided in the URL, the download will be denied.

* MY_TREE specifies the webtrees tree name
  * The default tree is specified in the control panel

* MY_FILENAME has to be provided without .ged extension, i.e. use this_file instead of this_file.ged
  * The tree name (MY_TREE) is taken as default if MY_FILENAME is not provided

* MY_PRIVACY_LEVEL specifies the user role, in which the GEDCOM export is executed
  * Valid values: none (default), gedadmin, user, visitor

* MY_EXPORT_FORMAT specifies the file format for the export
  * Valid values: gedcom (default), zip, zipmedia, gedzip

* MY_ENCODING specifies the encoding of the generated GEDCOM file
  * Valid values: UTF-8 (default), UTF-16BE, ANSEL, ASCII, CP1252

* MY_ENDINGS specifies the line endings in the generated GEDCOM file
  * Valid values: CRLF (default), LF

* MY_ACTION specifies whether the Gedcom file will be downloaded, saved on the server, or both
  * Valid values: download (default), save, both
  * The folder within the webtrees root path to save GEDCOM files can be specified in the module settings in the control panel 

* MY_TIME_STAMP specifies whether a (GMT) time stamp will be added to the file name of saved Gedcom files. MY_TIME_STAMP also defines whether the time stamp is added as prefix or as a postfix.
  * Valid values: none (default), prefix, postfix

* MY_GEDCOM7_FLAG specifies whether the generated GEDCOM file follows the GEDCOM 7 specification; default is GEDCOM 5.5.1
  * Valid values: 0 (default), 1
   
* MY_GEDCOM_L_FLAG specifies whether the GEDCOM-L standard shall be used additionally to GEDCOM 7
  * Valid values: 0 (default), 1

### Authorization Key in the Module Settings
The key parameter of the URL is checked against an authorization key. **The authorization key is specified and stored in the module settings**: Control Panel / Modules / All Modules / DownloadGedcomWithURL.

The provided authorization key needs to have a minimum length of 8 characters.

**The control panel also provides an option for the authorization key to be saved as an encrypted hash value**. This option is more secure, because the authorization key is not visible to anyone and also encrypted in the database. However, the authorization key is not readible any more (even for administrators) and cannot be recovered if it is forgotten.

### Default Values in the Module Settings
In the control panel, it is possible to provide default values for most of the URL parameters. These default settings are used if no specific parameter values are provided within the URL. By specifying the default values, the URLs to be called for a download can be simplified. If the default values shall be used for a download, it is sufficient to only provide the "key" parameter (authorization key) in the URL.

Any parameters provided in the URL have a higher priority and will overrule the default settings from the control panel.

![Screenshot: Default value settings in the control panel](resources/img/screenshot_control_panel_default_settings.jpg)

### Button to Test Downloads in the Module Settings
In the control panel, a "Test Download" button is available. If the button is pressed, a download is started with the current default settings of the control panel. This provides the possibility for a fast test of certain default values without calling a download URL.

In order to use changed settings for a test download, the settings need to be saved first.

![Screenshot: Test Download button in the control panel](resources/img/screenshot_control_panel_test_download_button.jpg)

### Example Scripts 
The release ZIP file also contains 3 example scripts for automatic download of Gedcom files or storage on the server:
+ **ExampleBashScript.sh** contains a simple example, how a GEDCOM file can be stored on the webtrees server. To trigger a store process in regular time intervals, this bash script could be triggered by a Cron Job.
+ **ExamplePythonScript.py** contains a simple example, how an automatic download can be performed with a Python script, e.g. on a Windows PC
+ **ExamplePythonScript2.py** contains a more advanced example for an automatic download with a Python script, which allows to store the downloaded file to a specific folder and also includes some logging output

### GEDCOM 7 export
DownloadGedcomWithURL also contains a (beta version) feature to download Gedcom files with the new GEDCOM 7 standard. In general, the **Gedcom 7 feature has a beta and testing status**. 

The GEDCOM 7 export also includes a further option to use the German GEDCOM-L standard.

The generated GEDCOM 7 files were successfully valdidated with [GEDCOM Validator](https://chronoplexsoftware.com/gedcomvalidator/) and 
[Gedcom Structure Analysis](https://ofb.hesmer.name/main_gsp_e.html#Analyse). Gedcom Structure Analysis also validates against the German GEDCOM-L standard.

At the moment, **it is not planned that the GEDCOM 7 feature is supported**. However, if you are a programmer, it might be interesting to take the code as a base for your own Gedcom 7 tests. The code of the Gedcom 7 export is contained in the GedcomSevenExportService.php file; mainly in the function convertToGedcom7. Pull request to the Github repository will be accepted, if improvements of the code are intended to be shared.

## Translation
You can help to translate this module. The translation is based on [gettext](https://en.wikipedia.org/wiki/Gettext) and uses .po files, which can be found in [/resources/lang/](https://github.com/Jefferson49/DownloadGedcomWithURL/tree/main/resources/lang). You can use a local editor like [Poedit](https://poedit.net/) or notepad++ to work on translations and provide them in the [Github repository](https://github.com/Jefferson49/DownloadGedcomWithURL) of the module. You can do this via a pull request (if you know how to do), or by opening a new issue and attaching a .po file. Updated translations will be included in the next release of this module.

Currently, the following languages are already available:
+ English
+ German

## Bugs and feature requests
If you experience any bugs or have a feature request for this webtrees custom module, you can [create a new issue](https://github.com/Jefferson49/DownloadGedcomWithURL/issues).

## Github Repository
https://github.com/Jefferson49/DownloadGedcomWithURL