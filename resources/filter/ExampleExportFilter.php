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

      //Only export the year of all birth dates, i.e. 01 JAN 1900 => 1900
      'INDI:BIRT:DATE'            => ["DATE .*([\d]{4})\n" => "DATE $1\n"],

      //Do not export baptism data
      '!INDI:BAPM'                => [],
      '!INDI:BAPM:*'              => [],

      //Remove RESN tag with value 'none', which is not allowed in the Gedcom 5.5.1 standard
      'INDI:RESN'                 => ["1 RESN (?i)none\n" => ""],

      'INDI'                      => [],
      'INDI:*'                    => [],      

      //Do not export marriage place data
      '!FAM:MARR:PLAC'            => [],
      '!FAM:MARR:PLAC:*'          => [],

      'FAM'                       => [],
      'FAM:*'                     => [],

      'NOTE'                      => [],
      'NOTE:*'                    => [],

      'OBJE'                      => [],

      //Change 'PDF' to 'pdf' in all FORM tags of media objects
      'OBJE:*'                    => ["2 FORM pdf" => "2 FORM PDF",
                                      "2 FORM jpg" => "2 FORM JPG",],

      'REPO'                      => [],
      'REPO:*'                    => [],

      'SOUR'                      => [],
      'SOUR:*'                    => [],

      //Perform a custom conversion for the SUBM record, 
      //i.e. call the PHP method $this->customConvert($pattern, $gedcom) to convert the Gedcom
      'SUBM'                      => ["->customConvert" => ""],
      'SUBM:*'                    => [],

      'TRLR'                      => [],
    ];

  /**
   * Custom conversion of a Gedcom string
   *
   * @param  string $pattern                  The pattern of the filter rule, e. g. INDI:BIRT:DATE
   * @param  string $gedcom                   The Gedcom to convert
   * @param  array  $empty_records_xref_list  List with all xrefs of empty records
   * 
   * @return string                           The converted Gedcom
   */
  public function customConvert(string $pattern, string $gedcom, array $empty_records_xref_list): string {

    //Create a specific record ID for submitters
    if ($pattern === 'SUBM') {
      
      //Get webtrees user if exists in change record, else use default
      preg_match_all("/2 _WT_USER (.*)\n/", $gedcom, $matches, PREG_SET_ORDER);
      $user = $matches[0][1] ?? 'Default';

      //Get XREF of SUBM
      preg_match_all("/0 @([^@]+)@ SUBM\n/", $gedcom, $matches, PREG_SET_ORDER);
      $xref = $matches[0][1] ?? '';

      //Create record ID number (RIN)
      $rin = "1 RIN " . $user . " (" . $xref . ")\n";

      //Add RIN to Gedcom
      $gedcom .= $rin;
    }

    return $gedcom;
  }
}
