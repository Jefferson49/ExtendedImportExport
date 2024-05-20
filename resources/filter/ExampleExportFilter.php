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
      'HEAD:SOUR'                => [],
      'HEAD:SUBM'                => [],
      'HEAD:GEDC'                => [],
      'HEAD:GEDC:VERS'           => [],
      'HEAD:GEDC:FORM'           => [],
      'HEAD:CHAR'                => [],

      'INDI'                     => [],
      'INDI:NAME'                => [],
      'INDI:SEX'                 => [],
      'INDI:BIRT'                => [],
      'INDI:BIRT:DATE'           => ["DATE [^\n]*([\d]{4})\n" => "DATE $1\n"],
      'INDI:DEAT'                => [],
      'INDI:DEAT:DATE'           => ["DATE [^\n]*([\d]{4})\n" => "DATE $1\n"],
      'INDI:FAMC'                => [],
      'INDI:FAMS'                => [],

      'FAM'                      => [],
      'FAM:HUSB'                 => [],
      'FAM:WIFE'                 => [],
      'FAM:CHIL'                 => [],
      'FAM:MARR:'                => [],
      'FAM:MARR:*'               => ["DATE [^\n]*([\d]{4})\n" => "DATE $1\n"],

      'OBJE'                     => [],
      'OBJE:*'                   => ["2 FORM pdf" => "2 FORM PDF",
                                     "2 FORM jpg" => "2 FORM JPG",],

      'SOUR'                     => [],

      'SUBM'                     => [],
      'SUBM:NAME'                => [],

      'TRLR'                     => [],
  ];
}
