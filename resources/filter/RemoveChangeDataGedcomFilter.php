<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * A GEDCOM filter, which removes CHAN structures
 */
class RemoveChangeDataGedcomFilter extends AbstractGedcomFilter implements GedcomFilterInterface
{    
    protected const GEDCOM_FILTER_RULES = [
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Remove CHAN data
        '!*:CHAN'                   => [],
        '!*:CHAN:*'                 => [],

        //Export other structures      
        '*'                         => [],
    ];
}
