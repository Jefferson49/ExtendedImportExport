<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * An export filter, which creates a CSV list (Surname, Given names) of all individuals 
 */
class IndividualNamesCsvExportFilter extends AbstractExportFilter implements ExportFilterInterface
{
    protected const EXPORT_FILTER = [
      
      //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
      //                             ["search pattern" => "replace pattern"],

      //Create the first line of the CSV file with column names (Surname, Given names)
      'HEAD'                      => [".*\n" => "\"Surname\",\"Given names\"\n"],

      //Remove all INDI records
      'INDI'                      => ["0 @([^@].+)@ INDI\n" => ""],

      //Replace certain characters (*,") by a custom conversion, i.e. call method $this->customConvert(...)
      //Generate CSV data from the individuals name
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
