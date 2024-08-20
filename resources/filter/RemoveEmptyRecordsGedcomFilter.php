<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

require_once __DIR__ . '/RemoveEmptyOrUnlinkedRecordsGedcomFilter.php';

use Fisharebest\Webtrees\I18N;

/**
 * A GEDCOM filter to remove empty records (FAM, NOTE, OBJE, REPO, SOUR)
 */
class RemoveEmptyRecordsGedcomFilter extends RemoveEmptyOrUnlinkedRecordsGedcomFilter
{
    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Remove empty records');
    } 

    /**
     * Custom conversion of a Gedcom string
     *
     * @param string        $pattern         The pattern of the filter rule, e. g. INDI:*:DATE
     * @param string        $gedcom          The Gedcom to convert
     * @param array         $records_list    A list with all xrefs and the related records: array <string xref => Record record>
     *                                       Records offer methods to be checked whether they are empty, referenced, etc.
     * @param array<string> $params          Parameters from remote URL requests as well as further parameters, e.g. 'tree' and 'base_url'
     * 
     * @return string                        The converted Gedcom
     */
    public function customConvert(string $pattern, string $gedcom, array &$records_list, array $params = []): string {

        //Call parent method for emtpy records only 
        $gedcom = parent::removeEmptyOrUnlinkedRecords($pattern, $gedcom, $records_list, true, false, true, false);
        
        return $gedcom;
    }   
}
