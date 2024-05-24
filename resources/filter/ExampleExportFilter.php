<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * Tag definitions and regular expressions for export filter
 */
class ExampleExportFilter implements ExportFilterInterface
{
    use ExportFilterTrait;
    public const EXPORT_FILTER = [
      
      //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
      //                             ["search pattern" => "replace pattern"],
      'HEAD'                     => [],
      'HEAD:*'                   => [],

      'INDI:BIRT:DATE'           => ["DATE [^\n]*([\d]{4})\n" => "DATE $1\n"],
      'INDI:DEAT:DATE'           => ["DATE [^\n]*([\d]{4})\n" => "DATE $1\n"],
      'INDI'                     => [],
      'INDI:*'                   => [],
      '!INDI:BAPM'               => [],
      '!INDI:BAPM:*'             => [],

      'FAM'                      => [],
      'FAM:*'                    => [],
      '!FAM:MARR:PLAC'           => [],
      '!FAM:MARR:PLAC:*'         => [],

      'OBJE'                     => [],
      'OBJE:*'                   => ["2 FORM pdf" => "2 FORM PDF",
                                     "2 FORM jpg" => "2 FORM JPG",],

      'SOUR'                     => [],

      'SUBM'                     => [],
      'SUBM:NAME'                => [],

      'TRLR'                     => [],
  ];
}
