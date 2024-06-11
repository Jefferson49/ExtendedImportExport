<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * An export filter, which exports no records; only HEAD, SUBM, TRLR
 */
class NoRecordsExportFilter extends AbstractExportFilter implements ExportFilterInterface
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
}
