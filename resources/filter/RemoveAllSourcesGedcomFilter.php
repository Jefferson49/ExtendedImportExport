<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\I18N;

/**
 * A GEDCOM filter, which removes all source records and their references
 */
class RemoveAllSourcesGedcomFilter extends AbstractGedcomFilter
{
    protected const GEDCOM_FILTER_RULES = [
      
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //You might want to insert some filter rules here, e.g. to remove or convert certain tag structures

        //Keep HEAD:SOUR
        'HEAD:SOUR'				    => [],
        'HEAD:SOUR:*'			    => [],

        //Remove all other sources
        '!SOUR'					    => [],
        '!SOUR:*'				    => [],
        '!*:SOUR'					=> [],
        '!*:SOUR:*'					=> [],
        '!*:*:SOUR'					=> [],
        '!*:*:SOUR:*'	    		=> [],
        '!*:*:*:SOUR'				=> [],
        '!*:*:*:SOUR:*'				=> [],

        //Export other structures
        '*'                         => [],
	];

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Remove all sources');
    }    
}
