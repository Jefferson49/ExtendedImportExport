<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

require_once __DIR__ . '/RemoveEmptyOrUnlinkedRecordsGedcomFilter.php';

use Fisharebest\Webtrees\I18N;

/**
 * A GEDCOM filter, which identifys individuals with SEX/FAMC/FAMS or less and removes their data
 * 
 * This filter is intended to be used if webtrees data shall be exported with privacy settings, which might create
 * INDI records with minimal data (i.e. SEX/FAMC/FAMS only). After applying this filter, the related INDI records 
 * will be empty and can be removed with the RemoveEmptyRecords GEDCOM filter.

 */
class ReduceMinimalIndividualsGedcomFilter extends RemoveEmptyOrUnlinkedRecordsGedcomFilter implements GedcomFilterInterface
{
    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Reduce minimal INDI records (with SEX, FAMC, FAMS or less) to empty INDI records');
    } 

    /**
     * Custom conversion of a Gedcom string
     *
     * @param string        $pattern         The pattern of the filter rule, e. g. INDI:*:DATE
     * @param string        $gedcom          The Gedcom to convert
     * @param array         $records_list    A list with all xrefs and the related records: array <string xref => Record record>
     * @param array<string> $params          Parameters from remote URL requests as well as further parameters, e.g. 'tree' and 'base_url'
     * 
     * @return string                        The converted Gedcom
     */
    public function customConvert(string $pattern, string $gedcom, array &$records_list, array $params = []): string {

        //Call parent method for emtpy records only 
        $gedcom = parent::removeEmptyOrUnlinkedRecords($pattern, $gedcom, $records_list, false, false, false, true);
        
        return $gedcom;
    }   
}
