<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * An export filter, which removes webtrees user structures (_WT_USER)
 */
class RemoveWebtreesUserExportFilter extends AbstractExportFilter implements ExportFilterInterface
{    
    protected const EXPORT_FILTER_RULES = [
        //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Remove _WT_USER
        '!*:_TODO:_WT_USER'         => [],
        '!*:CHAN:_WT_USER'          => [],

        //Export other structures      
        '*'                         => [],
    ];
}
