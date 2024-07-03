<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * An export filter, which removes CHAN structures
 */
class RemoveChangeDataExportFilter extends AbstractExportFilter implements ExportFilterInterface
{    
    protected const EXPORT_FILTER_RULES = [
        //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Remove CHAN data
        '!*:CHAN'                   => [],
        '!*:CHAN:*'                 => [],

        //Export other structures      
        '*'                         => [],
    ];
}
