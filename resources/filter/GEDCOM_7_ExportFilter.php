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

      //Modify header
      'HEAD'                      => [],
      '!HEAD:GEDC:FORM'           => [],
      '!HEAD:FILE'                => [],
      '!HEAD:CHAR'                => [],
      '!HEAD:SUBN'                => [],
      'HEAD:GEDC:VERS'            => ["2 VERS 5.5.1" => "2 VERS 7.0.14"],
      'HEAD:DATE'                 => ["0([\d]) (JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC) ([\d]{1,4})" => "$1 $2 $3"],
      'HEAD:LANG'                 => ["->customConvert" => ""],
      'HEAD:*'                    => [],

      //Remove submissions, because they do not exist in GEDCOM 7
      '!SUBN'                     => [],
      '!SUBN:*'                   => [],

      'TRLR'                      => [],

      //Apply custom conversion to all other records
      '*'                         => ["->customConvert" => ""],
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

      //Ignore SUBN, because it does not exist in GEDCOM 7

      //ToDo: How to handle GEDCOM_L?
      $gedcom = $this->convertToGedcom7($gedcom, true);
   
      return $gedcom;
   }

    /**
     * Convert to Gedcom 7
     *
     * @param string $gedcom
	 * @param bool $gedcom_l
     *
     * @return string
     */
    public function convertToGedcom7(string $gedcom, bool $gedcom_l= false): string
    {
		$replace_pairs = [
			"ROLE (Godparent)\n" => "ROLE GODP\n",
			"RELA godparent\n" => "RELA GODP\n",
			"RELA witness\n" => "RELA WITN\n",
            "1 _STAT NOT MARRIED\n" => "1 NO MARR\n",			//Convert former GEDCOM-L structure to new GEDCOM 7 structure
            "1 _STAT NEVER MARRIED\n" => "1 NO MARR\n",			//Convert former GEDCOM-L structure to new GEDCOM 7 structure
			"2 LANG SERB\n" => "2 LANG Serbian\n",				//Otherwise not found by language replacement below
			"2 LANG Serbo_Croa\n" => "2 LANG Serbo-Croatian\n",	//Otherwise not found by language replacement below
			"2 LANG BELORUSIAN\n" => "2 LANG Belarusian\n",		//Otherwise not found by language replacement below
		];
        
		foreach ($replace_pairs as $search => $replace) {
			$gedcom = str_replace($search, $replace, $gedcom);
		}

		$preg_replace_pairs = [
			//Date and age values
			"/0([\d]) (JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC) ([\d]{1,4})/" => "$1 $2 $3",
			"/([\d]) AGE 0([\d]{1,2})y/" => "$1 AGE $2y",
			"/([\d]) AGE ([\d]{1,3})y 0(.)m/" => "$1 AGE $2y $3m",
			"/([\d]) AGE ([\d]{1,3})y ([\d]{1,2})m 0([\d]{1,2})d/" => "$1 AGE $2y $3m $4d",
			"/([\d]) AGE ([\d]{1,2})m 00([\d])d/" => "$1 AGE $2m $3d",
			"/([\d]) AGE ([\d]{1,2})m 0([\d]{1,2})d/" => "$1 AGE $2m $3d",
			"/([\d]) AGE (<|>)([\d])/" => "$1 AGE $2 $3",
            "/@#DGREGORIAN@( |)/"  => 'GREGORIAN ',
            "/@#DJULIAN@( |)/"     => 'JULIAN ',
            "/@#DHEBREW@( |)/"     => 'HEBREW ',
            "/@#DFRENCH R@( |)/"   => 'FRENCH_R ',
            "/@#DROMAN@( |)/"      => 'ROMAN ',
            "/@#DUNKNOWN@( |)/"    => 'UNKNOWN ',

			//RELA, ROLE, ASSO
			"/([\d]) RELA/" => "$1 ROLE",
			"/([\d]) _ASSO/" => "$1 ASSO",

			//Media types
			//Allowed GEDCOM 7 media types: https://www.iana.org/assignments/media-types/media-types.xhtml
			//GEDCOM 5.5.1 media types: bmp | gif | jpg | ole | pcx | tif | wav
			"/2 FORM (bmp|BMP)(\n3 TYPE .[^\n]+)*/" => "2 FORM image/bmp",
			"/2 FORM (gif|GIF)(\n3 TYPE .[^\n]+)*/" => "2 FORM image/gif",
			"/2 FORM (jpg|JPG|jpeg|JPEG)(\n3 TYPE .[^\n]+)*/" => "2 FORM image/jpeg",
			"/2 FORM (tif|TIF|tiff|TIFF)(\n3 TYPE .[^\n]+)*/" => "2 FORM image/tiff",
			"/2 FORM (pdf|PDF)(\n3 TYPE .[^\n]+)*/" => "2 FORM application/pdf",
			"/2 FORM (emf|EMF)(\n3 TYPE .[^\n]+)*/" => "2 FORM image/emf",
			"/2 FORM (htm|HTM|html|HTML)(\n3 TYPE .[^\n]+)*/" => "2 FORM text/html",

            //Shared notes (SNOTE)
			"/([\d]) NOTE @(" . Gedcom::REGEX_XREF . ")@/" => "$1 SNOTE @$2@",
			"/0 @(" . Gedcom::REGEX_XREF . ")@ NOTE (.[^\n]+)/" => "0 @$1@ SNOTE $2",

            //External IDs (EXID)
			"/1 (AFN|RFN|RIN) (.[^\n]+)/" => "1 EXID $2\n2 TYPE https://gedcom.io/terms/v7/$1",
		];

		foreach ($preg_replace_pairs as $pattern => $replace) {
			$gedcom = preg_replace($pattern, $replace, $gedcom);
		}

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

		//Enum values for AGE: CHILD, INFANT, STILLBORN
        $AGE_ENUM_VALUES = [
            'CHILD'     => '< 8y', 
            'INFANT'    => '< 1y', 
            'STILLBORN' => '0y',
        ];

        $preg_pattern = [
			"/([\d]) AGE (CHILD|INFANT|STILLBORN)\n/",
        ];

        foreach ($preg_pattern as $pattern) {

            preg_match_all($pattern, $gedcom, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $level = (int) $match[1];

                $age_value    = $AGE_ENUM_VALUES[$match[2]];
                $phrase_value = $match[2];                    

                $search       = (string) $level . " AGE " . $phrase_value;
                $replace      = (string) $level . " AGE " . $age_value . "\n" .  (string) ($level + 1) . " PHRASE " . strtolower($phrase_value);
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

		//enumsets

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
