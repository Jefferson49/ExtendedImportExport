<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\I18N;

/**
 * A GEDCOM filter, which includes no records; only HEAD, SUBM, TRLR
 */
class NoRecordsGedcomFilter extends AbstractGedcomFilter
{
    protected const GEDCOM_FILTER_RULES = [
        
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Only include HEAD, SUBM, TRLR; nothing else
        'HEAD'                      => [],
        'HEAD:*'                    => [],

        //You might want to insert some filter rules here

        'SUBM'                      => [],      
        'SUBM:*'                    => [],      

        'TRLR'                      => [],
    ];

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('No records');
    }    
}
