<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

use Fisharebest\Webtrees\I18N;

/**
 * A GEDCOM filter, which identifys individuals with SEX/FAMC/FAMS only, and removes their data
 * 
 * This filter is intended to be used after webtrees data was exported with privacy settings, which might create
 * INDI records with minimal data (i.e. SEX/FAMC/FAMS only). After applying this filter, the related INDI records 
 * will be empty and can be removed with the RemoveEmptyRecords GEDCOM filter.
 */
class ReduceMinimalIndividualsGedcomFilter extends AbstractGedcomFilter implements GedcomFilterInterface
{
    protected const GEDCOM_FILTER_RULES = [
      
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        'INDI'                      => ["0 @([A-Za-z0-9:_.-]{1,20})@ INDI\n1 SEX [\w]\n$" => "0 @$1@ INDI\n",
                                        "0 @([A-Za-z0-9:_.-]{1,20})@ INDI\n1 (FAMC|FAMS) @[A-Za-z0-9:_.-]{1,20}@\n1 SEX [\w]\n$" => "0 @$1@ INDI\n",
                                        "0 @([A-Za-z0-9:_.-]{1,20})@ INDI\n1 (FAMC|FAMS) @[A-Za-z0-9:_.-]{1,20}@\n1 (FAMC|FAMS) @[A-Za-z0-9:_.-]{1,20}@\n1 SEX [\w]\n$" => "0 @$1@ INDI\n",],
        
        //Export other structures      
        '*'                         => [],        
    ];

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Reduce minimal individuals GEDCOM filter');
    } 
}
