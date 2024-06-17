<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * An export filter, which exports no records; only HEAD, SUBM, TRLR
 */
class Gedcom7ExportFilter extends AbstractExportFilter implements ExportFilterInterface
{
   protected const EXPORT_FILTER = [
      
      //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
      //                             ["search pattern" => "replace pattern"],
      'HEAD'                      => [],
      'HEAD:*'                    => [],

      'SUBM'                      => [],      
      'SUBM:*'                    => [],      

      'TRLR'                      => [],
   ];

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

      //Ignore SUBN in Gedcom 7 header
      //Ignore SUBN, because it does not exist in GEDCOM 7
      //$ignored_values = [Submission::RECORD_TYPE];
   
      return $gedcom;
   }
   
}
