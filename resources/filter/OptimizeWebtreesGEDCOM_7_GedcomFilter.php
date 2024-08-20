<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\I18N;

/**
 * A GEDCOM filter, which changes some of the webtrees GEDCOM structures in order to improve complicance to the GEDCOM 7.0 standard
 */
class OptimizeWebtreesGEDCOM_7_GedcomFilter extends AbstractGedcomFilter
{
    protected const GEDCOM_FILTER_RULES = [
        
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

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

    //Export other structures      
        '*'                         => [],
    ];

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Optimization of webtrees export for GEDCOM 7');
    } 
}
