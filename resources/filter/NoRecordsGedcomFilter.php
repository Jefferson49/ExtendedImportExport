<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * A GEDCOM filter, which includes no records; only HEAD, SUBM, TRLR
 */
class NoRecordsGedcomFilter extends AbstractGedcomFilter implements GedcomFilterInterface
{
    protected const GEDCOM_FILTER_RULES = [
        
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Only include HEAD, SUBM, TRLR; nothing else
        'HEAD'                      => [],
        'HEAD:*'                    => [],

        'SUBM'                      => [],      
        'SUBM:*'                    => [],      

        'TRLR'                      => [],
    ];
}
