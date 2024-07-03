<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * An export filter, which removes _TODO structures
 */
class RemoveToDoDataExportFilter extends AbstractExportFilter implements ExportFilterInterface
{    
    protected const EXPORT_FILTER_RULES = [
        //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Remove _TODO data
        '!FAM:_TODO'                => [],
        '!FAM:_TODO:*'              => [],
        '!INDI:_TODO'               => [],
        '!INDI:_TODO:*'             => [],

        //Export other structures      
        '*'                         => [],
    ];
}
