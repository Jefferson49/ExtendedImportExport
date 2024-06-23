<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * An export filter, which changes some of the webtrees export structures in order to be compliant to the GEDCOM 7.0 standard
 */
class OptimizeWebtreesExportFilter extends AbstractExportFilter implements ExportFilterInterface
{
   protected const EXPORT_FILTER_RULES = [
      
      //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
      //                             ["search pattern" => "replace pattern"],

      //Remove * from names (indicates first name underlined by webtrees)
      'INDI:NAME'                   => ["PHP_function" => "customConvert"],

      //Capitalize languages, because some other progams do not understand capital languages
      '*:LANG'                      => ["PHP_function" => "customConvert"],
      '*:*:LANG'                    => ["PHP_function" => "customConvert"],

      //Convert certain structures to lower case, because some other progams do not understand capital forms
      'OBJE:FILE:FORM'          	   => ["PHP_function" => "customConvert"],
      'INDI:FAMC:PEDI'          	   => ["PHP_function" => "customConvert"],
      'INDI:NAME:TYPE'          	   => ["PHP_function" => "customConvert"],
      'SOUR:REPO:CALN:MEDI'         => ["PHP_function" => "customConvert"],

      //Convert some structures to GEDCOM-L standard, because it is better to have some kind of standard than none
      'FAM:MARR:TYPE'            	=> ["2 TYPE (?i)RELIGIOUS" => "2 TYPE RELI",
                                        "2 TYPE (?i)CIVIL" => "2 TYPE CIVIL"],
		'INDI:ASSO:RELA'            	=> ["RegExp_macro" => "Godparent"],
		'*:*:_ASSO:RELA'            	=> ["RegExp_macro" => "Godparent"],

      //Allow RESN for INDI, FAM. However, remove 'RESN none' structures, because 'none' is not allowed by the standard
      //Note: OBJE:RESN is NOT allowed in GEDCOM 5.5.1
      'INDI:RESN'                   => ["1 RESN (?i)NONE\n" => ""],
      'FAM:RESN'                    => ["1 RESN (?i)NONE\n" => ""],

      //Remove RESN structures, where not allowed by the standard
      '!*:RESN'                     => [],
      '!*:*:RESN'                   => [],
      '!*:*:*:RESN'                 => [],

      //Remove CHAN and _WT_USER structures
      '!*:CHAN'                     => [],
      '!*:CHAN:*'                   => [],
      '!FAM:_TODO:_WT_USER'         => [],
      '!INDI:_TODO:_WT_USER'        => [],

   	//Export other structures      
      '*'                           => [],
   ];

   protected const REGEXP_MACROS = [
		//Name                        => Regular expression to be applied for the chosen GEDCOM tag
		//                               ["search pattern" => "replace pattern"],
      
      'Godparent'                   => ["([\d]) RELA (?i)GODPARENT" => "$1 RELA Godparent"],
   ];

    /**
     * Custom conversion of a Gedcom string
     *
     * @param string $pattern       The pattern of the filter rule, e. g. INDI:*:DATE
     * @param string $gedcom        The Gedcom to convert
     * @param array  $records_list  A list with all xrefs and the related records: array <string xref => Record record>
     * 
     * @return string               The converted Gedcom
     */
    public function customConvert(string $pattern, string $gedcom, array $records_list): string {

      if ($pattern === 'INDI:NAME') {

         //Remove all * characters from INDI:NAME
         $gedcom = str_replace('*' , '', $gedcom);
      }
      elseif (in_array($pattern, ['*:LANG', '*:*:LANG'])) {

         //Convert languages to capitalized string
         preg_match_all("/([\d]) LANG (.)(.*)/", $gedcom, $matches, PREG_SET_ORDER);

         foreach ($matches as $match) {
      
            $search =  $match[1] . " LANG " .            $match[2]  .            $match[3];
            $replace = $match[1] . " LANG " . strtoupper($match[2]) . strtolower($match[3]);
            $gedcom = str_replace($search, $replace, $gedcom);
         }
      }
      else {

         //Convert certain structures to lowercase string
         //OBJE:FILE:FORM, INDI:FAMC:PEDI, INDI:NAME:TYPE, SOUR:REPO:CALN:MEDI

         preg_match_all("/([\d]) (FORM|PEDI|TYPE|MEDI) (.*)/", $gedcom, $matches, PREG_SET_ORDER);

         foreach ($matches as $match) {
      
            $search =  $match[1]  . ' ' .  $match[2] . ' ' .  $match[3];
            $replace = $match[1]  . ' ' .  $match[2] . ' ' .  strtolower($match[3]);
            $gedcom = str_replace($search, $replace, $gedcom);
         }      
      }

      return $gedcom;
   }
}
