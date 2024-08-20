<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\I18N;

/**
 * A GEDCOM filter, which removes RESN structures
 */
class RemoveRestrictionsGedcomFilter extends AbstractGedcomFilter
{    
    protected const GEDCOM_FILTER_RULES = [
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Remove RESN structures
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

        return I18N::translate('Remove restrictions (i.e. RESN tags)');
    }     
}
