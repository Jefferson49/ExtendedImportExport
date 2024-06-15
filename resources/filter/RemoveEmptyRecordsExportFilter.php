<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * An export filter to remove empty records (FAM, NOTE, OBJE, REPO, SOUR)
 */
class RemoveEmptyRecordsExportFilter extends AbstractExportFilter implements ExportFilterInterface
{
   protected const EXPORT_FILTER = [
      
      //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
      //                             ["search pattern" => "replace pattern"],
      'HEAD'                      => [],
      'HEAD:*'                    => [],
      
      //Remove references to empty records
      '*:NOTE'                    => ["->customConvert" => ""],
      '*:*:NOTE'                  => ["->customConvert" => ""],
      '*:*:*:NOTE'                => ["->customConvert" => ""],

      '*:OBJE'                    => ["->customConvert" => ""],
      '*:*:OBJE'                  => ["->customConvert" => ""],
      '*:*:*:OBJE'                => ["->customConvert" => ""],
      '*:*:*:*:OBJE'              => ["->customConvert" => ""],

      'SOUR:REPO'                 => ["->customConvert" => ""],

      '*:SOUR'                    => ["->customConvert" => ""],
      '*:*:SOUR'                  => ["->customConvert" => ""],
      '*:*:*:SOUR'                => ["->customConvert" => ""],

      //Remove empty records or records without references
      'FAM'                       => ["->customConvert" => ""],
      'NOTE'                      => ["->customConvert" => ""],
      'OBJE'                      => ["->customConvert" => ""],
      'REPO'                      => ["->customConvert" => ""],
      'SOUR'                      => ["->customConvert" => ""],
      '_LOC'                      => ["->customConvert" => ""],

      'INDI'                      => [],
      'INDI:*'                    => [],

      'FAM:*'                     => [],

      'NOTE:*'                    => [],

      'OBJE:*'                    => [],

      'REPO:*'                    => [],

      'SOUR:*'                    => [],

      'SUBM'                      => [],
      'SUBM:*'                    => [],

      '_LOC:*'                    => [],

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

      //Empty records and records without a reference
      if (in_array($pattern, ['FAM', 'NOTE', 'OBJE', 'REPO', 'SOUR', '_LOC'])) {

         //ToDo webtrees::REGEX
         preg_match('/0 @([^@]+)@ ([A-Za-z1-9_]+)/', $gedcom, $match);
         $xref = $match[1] ?? '';

         if ($xref !== '') {

            $record = $records_list[$xref];

            //If record is empty or not referenced by other records, remove Gedcom
            if ($record->isEmpty() OR !$record->isReferenced()) {
               $gedcom = '';
            }   
         }
      }

      //Remove references, which do not point to records
      elseif (in_array($pattern, [
         '*:NOTE',
         '*:*:NOTE',
         '*:*:*NOTE',
   
         '*:OBJE',
         '*:*:OBJE',
         '*:*:*:OBJE',
         '*:*:*:*:OBJE',
   
         '*:REPO',         

         '*:SOUR',
         '*:*:SOUR',
         '*:*:*:SOUR',         
         ])) {

         //ToDo webtrees::REGEX   
         preg_match('/[\d] [\w]{4} @([^@]+)@/', $gedcom, $match);
         $xref = $match[1] ?? '';

         if ($xref !== '') {
            if (!$records_list[$xref]->isReferencing()) {
               $gedcom = '';
            }
         }
      }

      return $gedcom;
   }   
}
