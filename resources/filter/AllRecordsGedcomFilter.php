<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

use Cissee\WebtreesExt\MoreI18N;

/**
 * A GEDCOM filter, which includes all records (i.e. everything)
 */
class AllRecordsGedcomFilter extends AbstractGedcomFilter implements GedcomFilterInterface
{
    protected const GEDCOM_FILTER_RULES = [
      
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        '*'                         => [],
	];

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return MoreI18N::xlate('All records');
    }    
}
