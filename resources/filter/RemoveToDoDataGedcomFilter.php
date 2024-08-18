<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\I18N;

/**
 * A GEDCOM filter, which removes _TODO structures
 */
class RemoveToDoDataGedcomFilter extends AbstractGedcomFilter implements GedcomFilterInterface
{    
    protected const GEDCOM_FILTER_RULES = [
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Remove _TODO data
        '!FAM:_TODO'                => [],
        '!FAM:_TODO:*'              => [],
        '!INDI:_TODO'               => [],
        '!INDI:_TODO:*'             => [],

        //Export other structures      
        '*'                         => [],
    ];

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Remove ToDo data (i.e. _TODO structures)');
    }     
}
