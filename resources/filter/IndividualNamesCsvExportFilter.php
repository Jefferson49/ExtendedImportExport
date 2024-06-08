<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * Tag definitions and regular expressions for export filter
 */
class IndividualNamesCsvExportFilter implements ExportFilterInterface
{
    use ExportFilterTrait;
    public const EXPORT_FILTER = [
      
      //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
      //                             ["search pattern" => "replace pattern"],
      'HEAD'                      => [".*\n" => "\"Surname\",\"Given names\"\n"],

      'INDI'                      => ["0 @([^@].+)@ INDI\n" => ""],
      'INDI:NAME'                 => ["->customConvert" => '',
                                      "1 NAME (.*[^ ])? ?\/([^\/]*)\/(.*)\n" => "\"$2\",\"$1\"\n"],
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

    //Remove all * " , characters from INDI:NAME
    if ($pattern === 'INDI:NAME') {
        $gedcom = str_replace(['*', '"', ','] , ['', '', ''], $gedcom);
    }

    return $gedcom;
  }
}
