<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

use Fisharebest\Webtrees\I18N;

/**
 * A GEDCOM filter, which changes some of the webtrees GEDCOM structures in order to be compliant to the GEDCOM 7.0 standard
 */
class OptimizeWebtreesGEDCOM_7_GedcomFilter extends AbstractGedcomFilter implements GedcomFilterInterface
{
    protected const GEDCOM_FILTER_RULES = [
        
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Remove * from names (indicates first name underlined by webtrees)
        'INDI:NAME'                 => ["PHP_function" => "customConvert"],
        
        //Allow RESN for INDI, FAM, OBJE
        //However, remove RESN none structures, because 'none' is not allowed by the standard
        'INDI:RESN'                 => ["1 RESN (?i)NONE\n" => ""],
        '!INDI:NOTE:RESN'           => [],
        '!INDI:OBJE:RESN'           => [],
        '!INDI:SOUR:RESN'           => [],
        'INDI:*:RESN'               => ["1 RESN (?i)NONE\n" => ""],

        'FAM:RESN'                  => ["1 RESN (?i)NONE\n" => ""],
        '!FAM:NOTE:RESN'            => [],
        '!FAM:OBJE:RESN'            => [],
        '!FAM:SOUR:RESN'            => [],      
        'FAM:*:RESN'                => ["1 RESN (?i)NONE\n" => ""],

        'OBJE:RESN'                 => ["1 RESN (?i)NONE\n" => ""],

        //Remove RESN structures, where not allowed by the standard
        '!*:RESN'                   => [],
        '!*:*:RESN'                 => [],
        '!*:*:*:RESN'               => [],

        //Remove CHAN, _TODO, and _WT_USER structures
        '!*:CHAN'                   => [],
        '!*:CHAN:*'                 => [],
        '!FAM:_TODO:_WT_USER'       => [],
        '!INDI:_TODO:_WT_USER'      => [],

    //Export other structures      
        '*'                         => [],
    ];

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Optimize webtrees export for GEDCOM 7 filter');
    } 

    /**
     * Custom conversion of a Gedcom string
     *
     * @param string $pattern       The pattern of the filter rule, e. g. INDI:*:DATE
     * @param string $gedcom        The Gedcom to convert
     * @param array  $records_list  A list with all xrefs and the related records: array <string xref => Record record>
     * 
     * @return string               The converted Gedcom
     */
    public function customConvert(string $pattern, string $gedcom, array &$records_list): string {

        if ($pattern === 'INDI:NAME') {

            //Remove all * characters from INDI:NAME
            $gedcom = str_replace('*' , '', $gedcom);
        }

        return $gedcom;
    }
}
