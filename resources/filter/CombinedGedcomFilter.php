<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\I18N;

/**
 * A GEDCOM filter, which combines several other GEDCOM filters
 * 
 * In this example the filters are included "Before" the current GEDCOM filter.
 * An alternative method 'getIncludedFiltersAfter' can be used to include filters 'After' the current filter.
 */
class CombinedGedcomFilter extends AbstractGedcomFilter implements GedcomFilterInterface
{
    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Combined GEDCOM filter');
    }      

    /**
     * Include a set of other filters, which shall be executed before the current filter
     *
     * @return array<GedcomFilterInterface>    A set of included GEDCOM filters
     */
    public function getIncludedFiltersBefore(): array {

        return [
            new BirthMarriageDeathGedcomFilter(),
            new Gedcom_7_GedcomFilter(),
            new RemoveEmptyRecordsGedcomFilter(),      
        ];
    }
}
