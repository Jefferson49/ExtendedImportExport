<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\I18N;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * A GEDCOM filter, which converts GEDCOM 5.5.1 to GEDCOM 7.0
 */
class GEDCOM_7_GedcomFilter extends AbstractGedcomFilter
{
    //Mapping table of languages to IANA language tags
    private array $language_to_code_table;

    protected const GEDCOM_FILTER_RULES = [

        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Remove submissions, because they do not exist in GEDCOM 7
        '!SUBN'                     => [],
        '!SUBN:*'                   => [],

        //Date conversion
        '*:DATE'                 	=> ["RegExp_macro" => "DateConversion"],
        '*:*:DATE'                 	=> ["RegExp_macro" => "DateConversion"],
        '*:*:*:DATE'                => ["RegExp_macro" => "DateConversion"],
        '*:*:*:*:DATE'              => ["RegExp_macro" => "DateConversion"],

        //Age conversion
        '*:*:AGE'                 	=> ["RegExp_macro" => "AgeConversion"],
        '*:*:*:AGE'                	=> ["RegExp_macro" => "AgeConversion"],

        //Language conversion
        '*:LANG' 	           		=> ["RegExp_macro" => "LanguageConversion"],		
        '*:*:LANG'     	   			=> ["RegExp_macro" => "LanguageConversion"],		        

        //Modify header
        'HEAD'                      => [],
        '!HEAD:GEDC:FORM'           => [],
        '!HEAD:GEDC:FORM:*'         => [],
        '!HEAD:FILE'                => [],
        '!HEAD:FILE:*'              => [],
        '!HEAD:CHAR'                => [],
        '!HEAD:CHAR:*'              => [],
        '!HEAD:SUBN'                => [],
        '!HEAD:SUBN:*'              => [],
        'HEAD:GEDC:VERS'            => ["2 VERS 5.5.1" => "2 VERS 7.0.14"],
        'HEAD:*'                    => [],

        //External IDs (EXID)
        'INDI:AFN'                  => ["1 (AFN) (.+)" => "1 EXID $2\n2 TYPE https://gedcom.io/terms/v7/$1",],
        '*:RFN'                     => ["1 (RFN) (.+)" => "1 EXID $2\n2 TYPE https://gedcom.io/terms/v7/$1",],
        '*:RIN'                     => ["1 (RIN) (.+)" => "1 EXID $2\n2 TYPE https://gedcom.io/terms/v7/$1",],

        //RELA, ROLE, _ASSO	
        'INDI:ASSO'		            => ["RegExp_macro" => "ASSO_RELA"],
        'INDI:*:_ASSO'	 	        => ["RegExp_macro" => "ASSO_RELA"],
        'FAM:*:_ASSO'	            => ["RegExp_macro" => "ASSO_RELA"],

        '*:SOUR:EVEN:ROLE'          => ["RegExp_macro" => "ROLE_GodparentWitness"],
        '*:*:SOUR:EVEN:ROLE'        => ["RegExp_macro" => "ROLE_GodparentWitness"],
        
        //Media types
        //Allowed GEDCOM 7 media types: https://www.iana.org/assignments/media-types/media-types.xhtml
        //GEDCOM 5.5.1 media types: bmp | gif | jpg | ole | pcx | tif | wav
        'OBJE:FILE:FORM'            => ["2 FORM (?i)(BMP)"      => "2 FORM image/bmp",
                                        "2 FORM (?i)(GIF)"      => "2 FORM image/gif",
                                        "2 FORM (?i)(JPG|JPEG)" => "2 FORM image/jpeg",
                                        "2 FORM (?i)(TIFF)"     => "2 FORM image/tiff",
                                        "2 FORM (?i)(TIF)"      => "2 FORM image/tiff",
                                        "2 FORM (?i)(PDF)"      => "2 FORM application/pdf",
                                        "2 FORM (?i)(EMF)"      => "2 FORM image/emf",
                                        "2 FORM (?i)(HTM|HTML)" => "2 FORM text/html",],

        //Shared notes (SNOTE)
        '*:NOTE'					=> ["RegExp_macro" => "SharedNotes"],
        '*:*:NOTE'					=> ["RegExp_macro" => "SharedNotes"],
        '*:*:*:NOTE'				=> ["RegExp_macro" => "SharedNotes"],
        'NOTE'  					=> ["0 @([^@)]+)@ NOTE( ?)(.+)" => "0 @$1@ SNOTE$2$3"],

        //GEDCOM-L
        'INDI:*:_GODP'             	=> ["RegExp_macro" => "_GODP_WITN"],

        'FAM:*:_WITN'              	=> ["RegExp_macro" => "_GODP_WITN"],
        'INDI:*:_WITN'             	=> ["RegExp_macro" => "_GODP_WITN"],

        'FAM:_STAT'                 => ["1 _STAT (?i)(NOT|NEVER) MARRIED\n" => "1 NO MARR\n"],
                                        
        'FAM:MARR:TYPE'            	=> ["2 TYPE (?i)RELIGIOUS" => "2 TYPE RELI"],

        'TRLR'                      => [],

        //Apply custom conversion for ENUM values
        'INDI:NAME:TYPE'            => ["PHP_function" => "customConvert"],
        'INDI:FAMC:STAT'            => ["PHP_function" => "customConvert"],

        'INDI:ADOP:FAMC:ADOP'       => ["PHP_function" => "customConvert"],
        'INDI:FAMC:PEDI'            => ["PHP_function" => "customConvert"],

        'OBJE:FILE:FORM:TYPE'       => ["3 TYPE (.*)" => "3 MEDI $1",
                                        "PHP_function" => "customConvert"],

        '*:OBJE:FILE:FORM:MEDI'     => ["PHP_function" => "customConvert"],
        '*:*:OBJE:FILE:FORM:MEDI'   => ["PHP_function" => "customConvert"],
        '*:*:*:OBJE:FILE:FORM:MEDI' => ["PHP_function" => "customConvert"],
        'SOUR:REPO:CALN:MEDI'     	=> ["PHP_function" => "customConvert"],

        //'*:SOUR:EVEN:ROLE'		=> ["PHP_function" => "customConvert"],   	is handled in ROLE_GodparentWitness
        //'*:*:SOUR:EVEN:ROLE'		=> ["PHP_function" => "customConvert"],		is handled in ROLE_GodparentWitness

        '*:RESN'					=> ["PHP_function" => "customConvert"],
        '*:*:RESN'					=> ["PHP_function" => "customConvert"],

        //Export other records
        '*'							=> [],
    ];

   protected const REGEXP_MACROS = [
        //Macro Name                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        "DateConversion"			=> ["0([\d]) (JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC) ([\d]{1,4})" => "$1 $2 $3",
                                        "@#DGREGORIAN@( |)"  => 'GREGORIAN ',
                                        "@#DJULIAN@( |)"     => 'JULIAN ',
                                        "@#DHEBREW@( |)"     => 'HEBREW ',
                                        "@#DFRENCH R@( |)"   => 'FRENCH_R ',
                                        "@#DROMAN@( |)"      => 'ROMAN ',
                                        "@#DUNKNOWN@( |)"    => 'UNKNOWN ',
                                        "PHP_function" => "customConvert"],

        'LanguageConversion'		=> ["2 LANG (?i)SERB" => "2 LANG Serbian",
                                        "2 LANG (?i)SERBO_CROA" => "2 LANG Serbo-Croatian",
                                        "2 LANG (?i)BELORUSIAN" => "2 LANG Belarusian",
                                        "PHP_function" => "customConvert"],

        "AgeConversion"				=> ["([\d]) AGE 0([\d]{1,2})y" => "$1 AGE $2y",
                                        "([\d]) AGE ([\d]{1,3})y 0(.)m" => "$1 AGE $2y $3m",
                                        "([\d]) AGE ([\d]{1,3})y ([\d]{1,2})m 0([\d]{1,2})d" => "$1 AGE $2y $3m $4d",
                                        "([\d]) AGE ([\d]{1,2})m 00([\d])d" => "$1 AGE $2m $3d",
                                        "([\d]) AGE ([\d]{1,2})m 0([\d]{1,2})d" => "$1 AGE $2m $3d",
                                        "([\d]) AGE (<|>)([\d])" => "$1 AGE $2 $3",
                                        "([\d]) AGE (?i)CHILD" => "$1 AGE < 8y",
                                        "([\d]) AGE (?i)INFANT" => "$1 AGE < 1y",
                                        "([\d]) AGE (?i)STILLBORN" => "$1 AGE 0y"],

        "ASSO_RELA"					=> ["([\d]) RELA (?i)GODPARENT" => "$1 RELA GODP",
                                        "([\d]) RELA (?i)WITNESS" => "$1 RELA WITN",
                                        "([\d]) (_?)ASSO ([^\n]*)\n([\d]) RELA" => "$1 ASSO $3\n$4 ROLE",
                                        "PHP_function" => "customConvert"],

        "ROLE_GodparentWitness"		=> ["([\d]) ROLE \((?i)GODPARENT\)" => "$1 ROLE GODP",
                                        "([\d]) ROLE (?i)GODPARENT" => "$1 ROLE GODP",
                                        "([\d]) ROLE (?i)WITNESS" => "$1 ROLE WITN",
                                        "PHP_function" => "customConvert"],

        "SharedNotes"				=> ["([\d]) NOTE @([^@)]+)@" => "$1 SNOTE @$2@"],

        "_GODP_WITN"			    => ["2 _(GODP|WITN) (.*)" => "2 ASSO @VOID@\n3 PHRASE $2\n3 ROLE $1"],
	];

    private const ENUMSETS = [
        "ADOP" => ["HUSB", "WIFE", "BOTH",],
        "MEDI" => ["AUDIO", "BOOK","CARD", "ELECTRONIC", "FICHE", "FILM", "MAGAZINE", "MANUSCRIPT", "MAP", "NEWSPAPER", "PHOTO", "TOMBSTONE", "VIDEO", "OTHER",],
        "PEDI" => ["ADOPTED", "BIRTH", "FOSTER", "SEALING", "OTHER",],
        "RESN" => ["CONFIDENTIAL", "LOCKED", "PRIVACY",],
        "ROLE" => ["CHIL", "CLERGY", "FATH", "FRIEND", "GODP", "HUSB", "MOTH", "MULTIPLE", "NGHBR", "OFFICIATOR", "PARENT", "SPOU", "WIFE", "WITN", "OTHER",],
    ];

    private const NESTED_ENUMSETS = [
        [ "tags" => ["NAME", "TYPE"], "values" => ["AKA", "BIRTH", "IMMIGRANT", "MAIDEN", "MARRIED", "PROFESSIONAL", "OTHER",]],
        [ "tags" => ["FAMC", "STAT"], "values" => ["CHALLENGED", "DISPROVEN", "PROVEN",]],
    ];

    /**
     * Constructor
     *
     */      
    public function __construct() {

        $file_system = new Filesystem(new LocalFilesystemAdapter(__DIR__ . '/../iana/'));        
        $iana_language_registry = $file_system->read('iana_languages.txt');
        
        //Create language table
        preg_match_all("/Type: language\nSubtag: ([^\n]+)\nDescription: ([^\n]+)\n/", $iana_language_registry, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $this->language_to_code_table[strtoupper($match[2])]= $match[1];
        }   
    }

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('GEDCOM 7 conversion');
    }      

    /**
     * Custom conversion of a Gedcom string
     *
     * @param string        $pattern         The pattern of the filter rule, e. g. INDI:*:DATE
     * @param string        $gedcom          The Gedcom to convert
     * @param array         $records_list    A list with all xrefs and the related records: array <string xref => Record record>
     *                                       Records offer methods to be checked whether they are empty, referenced, etc.
     * @param array<string> $params          Parameters from remote URL requests as well as further parameters, e.g. 'tree' and 'base_url'
     * 
     * @return string                        The converted Gedcom
     */
    public function customConvert(string $pattern, string $gedcom, array &$records_list, array $params = []): string {

        if (strpos($pattern, ':DATE') > 0) {

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
        }
        elseif (strpos($pattern, ':LANG') > 0) {

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
        }
        elseif (in_array($pattern, ['INDI:NAME:TYPE', 'INDI:FAMC:STAT'])) {

            //Nested enumsets

            foreach (self::NESTED_ENUMSETS as $enumset) {

                $tags = $enumset["tags"];
                $enum_values = $enumset["values"];
                $level1_tag = $tags[0];
                $level2_tag = $tags[1];

                if ($pattern === 'INDI:' . $level1_tag . ':'. $level2_tag) {

                    preg_match_all("/2 " . $level2_tag . " (.*)/", $gedcom, $matches, PREG_SET_ORDER);

                    foreach ($matches as $match) {

                        $found_type =  $match[1];		

                        //If allowed type
                        if (in_array(strtoupper($found_type), $enum_values)) {
                            $search =  "2 " . $level2_tag . " " . $found_type;
                            $replace = "2 " . $level2_tag . " " . strtoupper($found_type);
                            $gedcom = str_replace($search, $replace, $gedcom);
                        }
                        //Use OTHER/PHRASE instead if OTHER is an allowed enum value for this type
                        elseif(in_array('OTHER', $enum_values))  {
                            $search =  "2 " . $level2_tag . " " . $found_type;
                            $replace = "2 " . $level2_tag . " OTHER\n3 PHRASE " . $found_type;
                            $gedcom = str_replace($search, $replace, $gedcom);
                        }
                    }		
                }
            }
        }
        else {

            //Enumsets

            foreach (self::ENUMSETS as $tag => $values) {

                preg_match_all("/([\d]) " . $tag . " (.+)/", $gedcom, $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    $level = (int) $match[1];

                    //If no known ENUM value, and OTHER is allowed for this enumtype, use OTHER/PHRASE instead 
                    if (!in_array(strtoupper($match[2]), $values) && in_array('OTHER', $values)) {
                        $search =  (string) $level . " " . $tag . " " . $match[2];
                        //For specific role descriptions
                        if ($tag == "ROLE") {
                            $match[2] = str_replace(['(', ')'], ['', ''], $match[2]);  // (<ROLE_DESCRIPTOR>)
                        }
                        $replace = (string) $level . " " . $tag . " OTHER\n" . (string) ($level + 1) . " PHRASE " . $match[2];
                        $gedcom = str_replace($search, $replace, $gedcom);
                    }
                    //Anyway, convert to upper case
                    else {
                        $search =  (string) $level . " " . $tag . " " . $match[2];
                        $replace = (string) $level . " " . $tag . " " . strtoupper($match[2]);
                        $gedcom = str_replace($search, $replace, $gedcom);
                    }					
                }
            }		
        }

        return $gedcom;
    }
}
