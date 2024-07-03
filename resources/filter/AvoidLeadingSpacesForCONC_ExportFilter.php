<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * An export filter, which avoids leading spaces from all CONC structures
 * 
 * Note: Trailing spaces will already be removed by the core webtrees export
 */
class AvoidLeadingSpacesForCONC_ExportFilter extends AbstractExportFilter implements ExportFilterInterface
{
    //Switch on wrapping without leading/trailing spaces
    protected const WRAP_LINES_WITHOUT_LEADING_AND_TRAILING_SPACES = true;

    protected const EXPORT_FILTER_RULES = [
      
        //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Export all
        '*'                         => [],
	];
}
