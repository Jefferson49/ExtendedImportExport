<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

use Fisharebest\Webtrees\Gedcom;

/**
 * An export filter, which converts GEDCOM 5.5.1 to GEDCOM 7.0
 */
class GEDCOM_7_ExportFilter extends AbstractExportFilter implements ExportFilterInterface
{
   //Mapping table of languages to IANA language tags
   private array $language_to_code_table;
   
   protected const EXPORT_FILTER_RULES = [
		
		//GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
		//                             ["search pattern" => "replace pattern"],

		//Date conversion
		'*:DATE'                 	=> ["RegExp_macro" => "DateConversion"],
		'*:*:DATE'                 	=> ["RegExp_macro" => "DateConversion"],
		'*:*:*:DATE'                => ["RegExp_macro" => "DateConversion"],
		'*:*:*:*:DATE'              => ["RegExp_macro" => "DateConversion"],

		//Age conversion
		'*:*:AGE'                 	=> ["RegExp_macro" => "AgeConversion"],
		'*:*:*:AGE'                	=> ["RegExp_macro" => "AgeConversion"],

		//Modify header
		'HEAD'                      => [],
		'!HEAD:GEDC:FORM'           => [],
		'!HEAD:FILE'                => [],
		'!HEAD:CHAR'                => [],
		'!HEAD:SUBN'                => [],
		'HEAD:GEDC:VERS'            => ["2 VERS 5.5.1" => "2 VERS 7.0.14"],
		'HEAD:LANG'                 => ["PHP_function" => "customConvert"],
		'HEAD:*'                    => [],

		//External IDs (EXID)
		'INDI:AFN'                  => ["1 AFN (.[^\n]+)" => "1 EXID $1\n2 TYPE https://gedcom.io/terms/v7/$1",],
		'*:RFN'                     => ["1 RFN (.[^\n]+)" => "1 EXID $1\n2 TYPE https://gedcom.io/terms/v7/$1",],
		'*:RIN'                     => ["1 RIN (.[^\n]+)" => "1 EXID $1\n2 TYPE https://gedcom.io/terms/v7/$1",],

		//RELA, ROLE, _ASSO	
		'INDI:ASSO:RELA'	    	=> ["RegExp_macro" => "RELA_GodparentWitness"],
		'INDI:ASSO'		            => ["RegExp_macro" => "ASSO_RELA"],
		'FAM:*:_ASSO:RELA'	        => ["RegExp_macro" => "RELA_GodparentWitness"],
		'FAM:*:_ASSO'	            => ["RegExp_macro" => "ASSO_RELA"],
		'INDI:*:_ASSO:RELA'	 	    => ["RegExp_macro" => "RELA_GodparentWitness"],
		'INDI:*:_ASSO'	 	        => ["RegExp_macro" => "ASSO_RELA"],

		'*:SOUR:EVEN:ROLE'          => ["RegExp_macro" => "ROLE_GodparentWitness"],
		'*:*:SOUR:EVEN:ROLE'        => ["RegExp_macro" => "ROLE_GodparentWitness"],
		
		//Media types
		//Allowed GEDCOM 7 media types: https://www.iana.org/assignments/media-types/media-types.xhtml
		//GEDCOM 5.5.1 media types: bmp | gif | jpg | ole | pcx | tif | wav
		'OBJE:FILE:FORM'            => ["2 FORM (?i)BMP(\n3 TYPE .[^\n]+)*" => "2 FORM image/bmp",
										"2 FORM (?i)GIF(\n3 TYPE .[^\n]+)*" => "2 FORM image/gif",
										"2 FORM (?i)(JPG|JPEG)(\n3 TYPE .[^\n]+)*" => "2 FORM image/jpeg",
										"2 FORM (?i)(TIF|TIFF)(\n3 TYPE .[^\n]+)*" => "2 FORM image/tiff",
										"2 FORM (?i)PDF(\n3 TYPE .[^\n]+)*" => "2 FORM application/pdf",
										"2 FORM (?i)EMF(\n3 TYPE .[^\n]+)*" => "2 FORM image/emf",
										"2 FORM (?i)(HTM|HTML)(\n3 TYPE .[^\n]+)*" => "2 FORM text/html",],

		//Shared notes (SNOTE)
		'*:NOTE'					=> ["RegExp_macro" => "SharedNotes"],
		'*:*:NOTE'					=> ["RegExp_macro" => "SharedNotes"],
		'*:*:*:NOTE'				=> ["RegExp_macro" => "SharedNotes"],
		'NOTE'  					=> ["0 @([^@)]+)@ NOTE( ?)(.+)" => "0 @$1@ SNOTE$2$3"],

		//Specific language issues
		'*:*:LANG'     	   			=> ["2 LANG (?i)SERB" => "2 LANG Serbian",
										"2 LANG (?i)SERBO_CROA" => "2 LANG Serbo-Croatian",
										"2 LANG (?i)BELORUSIAN" => "2 LANG Belarusian",],		

		//GEDCOM-L
		'INDI:*:_GODP'             	=> ["RegExp_macro" => "_GODP_WITN"],

		'FAM:*:_WITN'              	=> ["RegExp_macro" => "_GODP_WITN"],
		'INDI:*:_WITN'             	=> ["RegExp_macro" => "_GODP_WITN"],
	
		'FAM:STAT'                 	=> ["1 _STAT (?i)(NOT|NEVER) MARRIED\n" => "1 NO MARR\n"],
										
		'FAM:MARR:TYPE'            	=> ["2 TYPE (?i)RELIGIOUS" => "2 TYPE RELI"],

		//Remove submissions, because they do not exist in GEDCOM 7
		'!SUBN'                     => [],
		'!SUBN:*'                   => [],

		'TRLR'                      => [],

      	//Apply custom conversion to all other records
     	 '*'                        => ["PHP_function" => "customConvert"],
   ];
   protected const REGEXP_MACROS = [
		//Name                      => Regular expression to be applied for the chosen GEDCOM tag
		//                             ["search pattern" => "replace pattern"],

		"DateConversion"			=> ["0([\d]) (JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC) ([\d]{1,4})" => "$1 $2 $3",
										"@#DGREGORIAN@( |)"  => 'GREGORIAN ',
										"@#DJULIAN@( |)"     => 'JULIAN ',
										"@#DHEBREW@( |)"     => 'HEBREW ',
										"@#DFRENCH R@( |)"   => 'FRENCH_R ',
										"@#DROMAN@( |)"      => 'ROMAN ',
										"@#DUNKNOWN@( |)"    => 'UNKNOWN ',],

		"AgeConversion"				=> ["([\d]) AGE 0([\d]{1,2})y" => "$1 AGE $2y",
										"([\d]) AGE ([\d]{1,3})y 0(.)m" => "$1 AGE $2y $3m",
										"([\d]) AGE ([\d]{1,3})y ([\d]{1,2})m 0([\d]{1,2})d" => "$1 AGE $2y $3m $4d",
										"([\d]) AGE ([\d]{1,2})m 00([\d])d" => "$1 AGE $2m $3d",
										"([\d]) AGE ([\d]{1,2})m 0([\d]{1,2})d" => "$1 AGE $2m $3d",
										"([\d]) AGE (<|>)([\d])" => "$1 AGE $2 $3",
										"([\d]) AGE (?i)INFANT" => "$1 AGE CHILD"],

		"ASSO_RELA"					=> ["([\d]) (_?)ASSO (.*)\n([\d]) RELA" => "$1 $2ASSO $3\n$4 ROLE"],

		"RELA_GodparentWitness"		=> ["([\d]) RELA (?i)GODPARENT" => "$1 ROLE GODP",
										"([\d]) RELA (?i)WITNESS" => "$1 ROLE WITN",],

		"ROLE_GodparentWitness"		=> ["3 ROLE \((?i)GODPARENT\)" => "3 ROLE GODP",
										"3 ROLE (?i)GODPARENT" => "3 ROLE GODP",
										"3 ROLE (?i)WITNESS" => "3 ROLE WITN",],

		"SharedNotes"				=> ["([\d]) NOTE @([^@)]+)@" => "$1 SNOTE @$2@"],

		"_GODP_WITN"			      => ["2 _(_GODP|_WITN) (.*)" => "2 ASSO @VOID@\n3 PHRASE $2\n3 ROLE $1"],
	];

	/**
	 * Constructor
	 *
	 */      
	public function __construct() {

      $iana_language_registry_file_name = __DIR__ . '/../../vendor/iana/iana_languages.txt';
      $iana_language_registry = file_get_contents($iana_language_registry_file_name);
   
      //Create language table
      preg_match_all("/Type: language\nSubtag: ([^\n]+)\nDescription: ([^\n]+)\n/", $iana_language_registry, $matches, PREG_SET_ORDER);
   
      foreach ($matches as $match) {
         $this->language_to_code_table[strtoupper($match[2])]= $match[1];
      }   
   }

   /**
    * Custom conversion of a Gedcom string
    *
    * @param string $pattern       The pattern of the filter rule, e. g. INDI:BIRT:DATE
    * @param string $gedcom        The Gedcom to convert
    * @param array  $records_list  A list with all xrefs and the related records: array <string xref => Record record>
    * 
    * @return string               The converted Gedcom
    */
   public function customConvert(string $pattern, string $gedcom, array $records_list): string {

		//Specific dates with slashes in years, eg. 1741/42
        $preg_pattern = [
			"/([\d]) DATE ([\d]{0,2})([ ]*)(|JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)([ ]*)([\d]{1,4})\/([\d]{2})\n/",
        ];

        foreach ($preg_pattern as $pattern) {

            preg_match_all($pattern, $gedcom, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $level = (int) $match[1];

                $date_value   = $match[2] . $match[3] . $match[4] . $match[5]  . $match[6];
                $phrase_value = $date_value . "/" . $match[7];
                $search       = (string) $level . " DATE " . $phrase_value;
                $replace      = (string) $level . " DATE " . $date_value . "\n" .  (string) ($level + 1) . " PHRASE " . $phrase_value;
                $gedcom       = str_replace($search, $replace, $gedcom);
            }			
        }        

		//DATE INT (date interpretation)
        $preg_pattern = [
			"/([\d]) DATE INT ([^\(]+) \(([^)]+)\)\n/",
        ];

        foreach ($preg_pattern as $pattern) {

            preg_match_all($pattern, $gedcom, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $level = (int) $match[1];

                $date_value   = $match[2];
                $phrase_value = 'interpratation: ' . $match[3];
                $search       = (string) $level . " DATE INT " . $date_value . ' (' . $match[3] . ')';
                $replace      = (string) $level . " DATE " . $date_value . "\n" .  (string) ($level + 1) . " PHRASE " . $phrase_value;
                $gedcom       = str_replace($search, $replace, $gedcom);
            }			
        }        
           
		//Languages
		//Allowed GEDCOM 7 language tags: https://www.iana.org/assignments/language-subtag-registry/language-subtag-registry

		preg_match_all("/([12]) LANG (.[^\n]+)\n/", $gedcom, $matches, PREG_SET_ORDER);

		foreach ($matches as $match) {

			if (isset($this->language_to_code_table[strtoupper($match[2])])) {

				$search =  $match[1] . " LANG " . $match[2] . "\n";
				$replace = $match[1] . " LANG " . $this->language_to_code_table[strtoupper($match[2])] . "\n";
				$gedcom = str_replace($search, $replace, $gedcom);
			}
		}

		//Enumsets

		$enumsets = [
			"ADOP" => ["HUSB", "WIFE", "BOTH",],
			"MEDI" => ["AUDIO", "BOOK","CARD", "ELECTRONIC", "FICHE", "FILM", "MAGAZINE", "MANUSCRIPT", "MAP", "NEWSPAPER", "PHOTO", "TOMBSTONE", "VIDEO", "OTHER",],
			"PEDI" => ["ADOPTED", "BIRTH", "FOSTER", "SEALING", "OTHER",],
			"QUAY" => ["1", "2", "3",],
			"RESN" => ["CONFIDENTIAL", "LOCKED", "PRIVACY",],
			"ROLE" => ["CHIL", "CLERGY", "FATH", "FRIEND", "GODP", "HUSB", "MOTH", "MULTIPLE", "NGHBR", "OFFICIATOR", "PARENT", "SPOU", "WIFE", "WITN", "OTHER",],
			"SEX" =>  ["M", "F", "X", "U",],
		];

		foreach ($enumsets as $enumset => $values) {

			preg_match_all("/([\d]) " . $enumset . " (.[^\n]+)/", $gedcom, $matches, PREG_SET_ORDER);

			foreach ($matches as $match) {
				$level = (int) $match[1];

				//If allowed value, convert to upper case
				if (in_array(strtoupper($match[2]), $values)) {
					$search =  (string) $level . " " . $enumset . " " . $match[2];
					$replace = (string) $level . " " . $enumset . " " . strtoupper($match[2]);
					$gedcom = str_replace($search, $replace, $gedcom);
				}
				//If phrase is allowed for this enumtype, use phrase instead 
				elseif (in_array($enumset, ["ADOP", "MEDI", "PEDI", "ROLE"])){
					$search =  (string) $level . " " . $enumset . " " . $match[2];
                    //For specific role descriptions
                    if ($enumset == "ROLE") {
                        $match[2] = str_replace(['(', ')'], ['', ''], $match[2]);  // (<ROLE_DESCRIPTOR>)
                    }
					$replace = (string) $level . " " . $enumset . " OTHER\n" . (string) ($level + 1) . " PHRASE " . $match[2];
					$gedcom = str_replace($search, $replace, $gedcom);
				}
			}
		}		

		//Nested enumsets

		$nested_enumsets = [
			[ "tags" => ["NAME", "TYPE"], "values" => ["AKA", "BIRTH", "IMMIGRANT", "MAIDEN", "MARRIED", "PROFESSIONAL",]],
			[ "tags" => ["FAMC", "STAT"], "values" => ["CHALLENGED", "DISPROVEN", "PROVEN",]],
		];

		foreach ($nested_enumsets as $enumset) {

			$tags = $enumset["tags"];
			$enum_values = $enumset["values"];
			$level1_tag = $tags[0];
			$level2_tag = $tags[1];

			preg_match_all("/([\d]) " . $level1_tag . " (.[^\n]+)\n([\d]) " . $level2_tag . " (.[^\n]+)/", $gedcom, $matches, PREG_SET_ORDER);

			foreach ($matches as $match) {

				$size = sizeof($match);
				$level = (int) $match[$size - 2];
				$found_type =  $match[$size - 1];		

				//If allowed type
				if (in_array(strtoupper($found_type), $enum_values)) {
					$search =  (string) $level . " " . $level2_tag . " " . $found_type;
					$replace = (string) $level . " " . $level2_tag . " " . strtoupper($found_type);
					$gedcom = str_replace($search, $replace, $gedcom);
				}
				//Use OTHER/PHRASE instead
				else {
					$search =  (string) $level  . " " . $level2_tag . " " . $found_type;
					$replace = (string) $level  . " " . $level2_tag . " OTHER\n" . (string) ($level + 1) . " PHRASE " . $found_type;
					$gedcom = str_replace($search, $replace, $gedcom);
				}
			}	
		}	

		return $gedcom;
	}
}
