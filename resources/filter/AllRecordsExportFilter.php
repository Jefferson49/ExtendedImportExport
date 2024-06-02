<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * Tag definitions and regular expressions for export filter
 */
class AllRecordsExportFilter implements ExportFilterInterface
{
    use ExportFilterTrait;
    public const EXPORT_FILTER = [
      
      //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
      //                             ["search pattern" => "replace pattern"],
      'HEAD'                      => [],
      'HEAD:*'                    => [],

      'INDI'                      => [],
      'INDI:*'                    => [],

      'FAM'                       => [],
      'FAM:*'                     => [],
 
      'NOTE'                      => [],
      'NOTE:*'                    => [],

      'OBJE'                      => [],
      'OBJE:*'                    => [],

      'REPO'                      => [],
      'REPO:*'                    => [],

      'SOUR'                      => [],
      'SOUR:*'                    => [],

      'SUBM'                      => [],
      'SUBM:*'                    => [],

      '_LOC'                      => [],
      '_LOC:*'                    => [],

      'TRLR'                      => [],
   ];
}
