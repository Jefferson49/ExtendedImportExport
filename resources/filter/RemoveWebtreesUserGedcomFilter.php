<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

use Fisharebest\Webtrees\I18N;

/**
 * A GEDCOM filter, which removes webtrees user structures (_WT_USER)
 */
class RemoveWebtreesUserGedcomFilter extends AbstractGedcomFilter implements GedcomFilterInterface
{    
    protected const GEDCOM_FILTER_RULES = [
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Remove _WT_USER
        '!*:_TODO:_WT_USER'         => [],
        '!*:CHAN:_WT_USER'          => [],

        //Export other structures      
        '*'                         => [],
    ];

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Remove webtrees user data (i.e. _WT_USER tags)');
    }     
}
