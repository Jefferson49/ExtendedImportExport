<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * An export filter, which exports all records (i.e. everything)
 */
class AllRecordsExportFilter extends AbstractExportFilter implements ExportFilterInterface
{
    protected const EXPORT_FILTER_RULES = [
      
        //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        '*'                         => [],
	];
}
