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
      'HEAD'                      => [],
      'HEAD:*'                    => [],

      'INDI:BIRT:DATE'            => ["DATE .*([\d]{4})\n" => "DATE $1\n"],
      '!INDI:BAPM'                => [],
      '!INDI:BAPM:*'              => [],
      'INDI'                      => [],
      'INDI:*'                    => [],

      '!FAM:MARR:PLAC'            => [],
      '!FAM:MARR:PLAC:*'          => [],
      'FAM'                       => [],
      'FAM:*'                     => [],

      'NOTE'                      => [],
      'NOTE:*'                    => [],

      'OBJE'                      => [],
      'OBJE:*'                    => ["2 FORM pdf" => "2 FORM PDF",
                                      "2 FORM jpg" => "2 FORM JPG",],

      'REPO'                      => [],
      'REPO:*'                    => [],

      'SOUR'                      => ['->customConvert' => ''],
      'SOUR:*'                    => [],

      'SUBM'                      => [],
      'SUBM:NAME'                 => [],
      
      '_LOC'                      => [],
      '_LOC:*'                    => [],

      'TRLR'                      => [],
    ];

  /**
   * Custom conversion of a Gedcom string
   *
   * @param  string $pattern  The pattern of the filter rule, e. g. INDI:BIRT:DATE
   * @param  string $gedcom   The Gedcom to convert
   * 
   * @return string           The converted Gedcom
   */
  public function customConvert(string $pattern, string $gedcom): string {

    //Set all sources to private (i.e. 1 RESN PRIVACY)
    if ($pattern === 'SOUR') {
      
      //If restricion already exists, change it to privacy
      if (preg_match("/1 RESN .*\n/", $gedcom)) {

        $gedcom = preg_replace("/1 RESN (.*)\n/", "1 RESN PRIVACY\n", $gedcom) ?? '';
      }
      //Otherwise add privacy restriction
      else {
        $gedcom .= "1 RESN PRIVACY\n";
      }
    }

    return $gedcom;
  }
}
