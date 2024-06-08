<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * Tag definitions and regular expressions for export filter
 */
class RemoveEmptyRecordsExportFilter implements ExportFilterInterface
{
    use ExportFilterTrait;
    public const EXPORT_FILTER = [
      
      //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
      //                             ["search pattern" => "replace pattern"],
      'HEAD'                      => [],
      'HEAD:*'                    => [],
      
      'INDI'                      => [],
      'INDI:SOUR'                 => ["->customConvert" => ""],
      'INDI:PROP:SOUR'            => ["->customConvert" => ""],
      'INDI:*'                    => [],

      'FAM'                       => [],
      'FAM:*'                     => [],
 
      'NOTE'                      => ["->customConvert" => ""],
      'NOTE:*'                    => [],

      'OBJE'                      => ["->customConvert" => ""],
      'OBJE:*'                    => [],

      'REPO'                      => ["->customConvert" => ""],
      'REPO:*'                    => [],

      'SOUR'                      => ["->customConvert" => ""],
      'SOUR:*'                    => [],

      'SUBM'                      => [],
      'SUBM:*'                    => [],

      '_LOC'                      => [],
      '_LOC:*'                    => [],

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

      if (in_array($pattern, ['NOTE', 'OBJE', 'REPO', 'SOUR',])) {

         preg_match('/0 @([^@]+)@ ([A-Za-z1-9_]+)/', $gedcom, $match);
         $xref = $match[1] ?? '';

         //If xref is in empty records list, remove Gedcom
         if (in_array($xref, $empty_records_xref_list)) {
            $gedcom = '';
         }
      }
      elseif (in_array($pattern, ['*:SOUR', '*:*:SOUR',])) {

         preg_match('/[\d] SOUR @([^@]+)@/', $gedcom, $match);
         $xref = $match[1] ?? '';

         //If xref is in empty records list, remove Gedcom
         if (in_array($xref, $empty_records_xref_list)) {
            $gedcom = '';
         }
      }

      return $gedcom;
   }   
}
