<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\I18N;

/**
 * A GEDCOM filter, which removes all notes and their references
 */
class RemoveAllNotesGedcomFilter extends AbstractGedcomFilter
{
    protected const GEDCOM_FILTER_RULES = [
      
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //You might want to insert some filter rules here, e.g. to remove or convert certain tag structures

        '!NOTE'					    => [],
        '!NOTE:*'				    => [],
        '!*:NOTE'					=> [],
        '!*:NOTE:*'					=> [],
        '!*:*:NOTE'					=> [],
        '!*:*:NOTE:*'	    		=> [],
        '!*:*:*:NOTE'				=> [],
        '!*:*:*:NOTE:*'				=> [],

        //Export other structures
        '*'                         => [],
	];

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Remove all notes');
    }    
}
