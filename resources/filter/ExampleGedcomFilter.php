<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\I18N;

/**
 * An example GEDCOM filter, which demonstrates the different options, which can be used in GEDCOM filters
 */
class ExampleGedcomFilter extends AbstractGedcomFilter
{
    protected const GEDCOM_FILTER_RULES = [

        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Only include the year of all birth dates, i.e. 01 JAN 1900 => 1900
        'INDI:BIRT:DATE'            => ["DATE .*([\d]{4})\n" => "DATE $1\n"],

        //Do not include baptism data
        '!INDI:BAPM'                => [],
        '!INDI:BAPM:*'              => [],

        //Remove RESN tag with value 'none', because it is not allowed in the Gedcom 5.5.1 standard
        'INDI:RESN'                 => ["1 RESN (?i)none\n" => ""],

        //Do not include marriage place data
        '!FAM:MARR:PLAC'            => [],
        '!FAM:MARR:PLAC:*'          => [],

        //Apply several regular expressikon replacements to one tag pattern. In this case, 2 replacements are used 
        //Change 'pdf'/'jpg' to 'PDF'/'JPG' in all FORM tags of media objects
        'OBJE:*'                    => ["2 FORM pdf" => "2 FORM PDF",
                                        "2 FORM jpg" => "2 FORM JPG",],

        //Perform a custom conversion for the SUBM record, 
        //i.e. call the method $this->customConvert(...) to convert the Gedcom. 
        //The methd is implemented in the PHP code below
        'SUBM'                      => ["PHP_function" => "customConvert"],

        //Export all other GEDCOM structures      
        '*'                         => [],
    ];

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Example GEDCOM filter');
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

        //Create a specific record ID for submitters
        if ($pattern === 'SUBM') {
        
        //Get webtrees user if exists in change record, else use default
        preg_match_all("/2 _WT_USER (.*)\n/", $gedcom, $matches, PREG_SET_ORDER);
        $user = $matches[0][1] ?? 'Default';

        //Get XREF of SUBM
        preg_match_all("/0 @(" . Gedcom::REGEX_XREF . ")@ SUBM\n/", $gedcom, $matches, PREG_SET_ORDER);
        $xref = $matches[0][1] ?? '';

        //Create record ID number (RIN)
        $rin = "1 RIN " . $user . " (" . $xref . ")\n";

        //Add RIN to Gedcom
        $gedcom .= $rin;
        }

        return $gedcom;
    }
}
