<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

use Fisharebest\Webtrees\Gedcom;

/**
 * An export filter to remove empty records (FAM, NOTE, OBJE, REPO, SOUR)
 */
class RemoveEmptyRecordsExportFilter extends RemoveEmptyOrUnlinkedRecordsExportFilter implements ExportFilterInterface
{
   /**
    * Custom conversion of a Gedcom string
    *
    * @param string $pattern       The pattern of the filter rule, e. g. INDI:BIRT:DATE
    * @param string $gedcom        The Gedcom to convert
    * @param array  $records_list  A list with all xrefs and the related records: array <string xref => Record record>
    * 
    * @return string               The converted Gedcom
    */
   public function customConvert(string $pattern, string $gedcom, array $records_list): string {

      //Call parent method for emtpy records only 
      $gedcom = parent::removeEmptyAndUnlikedRecords($pattern, $gedcom, $records_list, true, false);
      return $gedcom;
   }   
}
