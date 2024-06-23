<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

use Fisharebest\Webtrees\Gedcom;

/**
 * An export filter to remove empty and unlinked records (FAM, NOTE, OBJE, REPO, SOUR, _LOC)
 */
class RemoveEmptyOrUnlinkedRecordsExportFilter extends AbstractExportFilter implements ExportFilterInterface
{
   protected const USES_REFERENCES_ANALYSIS = true;
   protected const EXPORT_FILTER_RULES = [
      
      //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
      //                             ["search pattern" => "replace pattern"],
      'HEAD'                      => [],
      'HEAD:*'                    => [],
      
      //Remove references to empty records
      '*:NOTE'                    => ["PHP_function" => "customConvert"],
      '*:*:NOTE'                  => ["PHP_function" => "customConvert"],
      '*:*:*:NOTE'                => ["PHP_function" => "customConvert"],

      '*:OBJE'                    => ["PHP_function" => "customConvert"],
      '*:*:OBJE'                  => ["PHP_function" => "customConvert"],
      '*:*:*:OBJE'                => ["PHP_function" => "customConvert"],
      '*:*:*:*:OBJE'              => ["PHP_function" => "customConvert"],

      'SOUR:REPO'                 => ["PHP_function" => "customConvert"],

      '*:SOUR'                    => ["PHP_function" => "customConvert"],
      '*:*:SOUR'                  => ["PHP_function" => "customConvert"],
      '*:*:*:SOUR'                => ["PHP_function" => "customConvert"],

      //Remove empty records or records without references
      'FAM'                       => ["PHP_function" => "customConvert"],
      'NOTE'                      => ["PHP_function" => "customConvert"],
      'OBJE'                      => ["PHP_function" => "customConvert"],
      'REPO'                      => ["PHP_function" => "customConvert"],
      'SOUR'                      => ["PHP_function" => "customConvert"],
      '_LOC'                      => ["PHP_function" => "customConvert"],

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

      $gedcom = $this->removeEmptyOrUnlinkedRecords($pattern, $gedcom, $records_list, true, true);
      return $gedcom;
   }   

   /**
    * Analyze a Gedcom record, if it is empty or unlinked (i.e. not referenced) by other records. If yes, return empty Gedcom.
    *
    * @param string $pattern          The pattern of the filter rule, e. g. INDI:BIRT:DATE
    * @param string $gedcom           The Gedcom to convert
    * @param array  $records_list     A list with all xrefs and the related records: array <string xref => Record record>
    * @param bool   $remove_empty     Whether empty records shall be removed
    * @param bool   $remove_unlinked  Whether empty records shall be removed
    * 
    * @return string                  The converted Gedcom
    */
    public function removeEmptyOrUnlinkedRecords(string $pattern, string $gedcom, array $records_list, bool $remove_empty, bool $remove_unlinked): string {

      //Empty records and records without a reference
      if (in_array($pattern, ['FAM', 'NOTE', 'OBJE', 'REPO', 'SOUR', '_LOC'])) {

         preg_match('/0 @(' . Gedcom::REGEX_XREF  . ')@ (' . Gedcom::REGEX_TAG  . ')/', $gedcom, $match);
         $xref = $match[1] ?? '';

         if ($xref !== '') {

            $record = $records_list[$xref];

            //If record is empty or not referenced by other records, remove Gedcom
            if (     ($remove_empty    &&  $record->isEmpty()) 
               	OR ($remove_unlinked && !$record->isReferenced())) {

               $gedcom = '';
            }   
         }
      }

      //Remove references, which point to empty records
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

         preg_match('/[\d] [\w]{4} @(' . Gedcom::REGEX_XREF . ')@/', $gedcom, $match);
         $xref = $match[1] ?? '';
      
         if ($xref !== '') {

            //If referenced record is empty, remove Gedcom
            if ($records_list[$xref]->isEmpty()) {

               $gedcom = '';
            }
         }
      }

      return $gedcom;
   }   
}
