<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * A GEDCOM filter, which avoids leading spaces from all CONC structures
 * 
 * Note: Trailing spaces will already be removed by the core webtrees export
 */
class AvoidLeadingSpacesForCONC_GedcomFilter extends AbstractGedcomFilter implements GedcomFilterInterface
{
    //Switch on wrapping without leading/trailing spaces
    protected const WRAP_LINES_WITHOUT_LEADING_AND_TRAILING_SPACES = true;

    protected const GEDCOM_FILTER_RULES = [
      
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Export all
        '*'                         => [],
	];
}
