<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

use Fisharebest\Webtrees\Tree;

/**
 * An export filter, which combines two export filters
 */
class CombinedExportFilter extends AbstractExportFilter implements ExportFilterInterface
{
    /**
     * Return a combined filter
     * 
     * @param Tree $tree
     *
     * @return array
     */
    public function getExportFilter(Tree $tree = null): array {

      $filter1 = new NoRecordsExportFilter();
      $filter2 = new ExampleExportFilter();

      return $this->mergeFilterRules($filter1->getExportFilter($tree), $filter2->getExportFilter($tree));
    }   

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

      $filter1 = new NoRecordsExportFilter();
      $filter2 = new BirthMarriageDeathExportFilter();
      
      $gedcom = $filter1->customConvert($pattern, $gedcom, $records_list);
      $gedcom = $filter2->customConvert($pattern, $gedcom, $records_list);

      return $gedcom;
    }

}
