<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\I18N;

/**
 * A GEDCOM filter to remove empty and unlinked records (FAM, NOTE, OBJE, REPO, SOUR, _LOC)
 */
class RemoveEmptyOrUnlinkedRecordsGedcomFilter extends AbstractGedcomFilter implements GedcomFilterInterface
{
    protected const USES_REFERENCES_ANALYSIS = true;
    protected const GEDCOM_FILTER_RULES = [
        
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],
        'HEAD'                      => [],
        'HEAD:*'                    => [],
        
        //Remove references to empty records
        'FAM:CHIL'                  => ["PHP_function" => "customConvert"],
        'FAM:HUSB'                  => ["PHP_function" => "customConvert"],
        'FAM:WIFE'                  => ["PHP_function" => "customConvert"],
        'FAM:*:_ASSO'               => ["PHP_function" => "customConvert"],        

        'INDI:ALIA'                 => ["PHP_function" => "customConvert"],
        'INDI:ASSO'                 => ["PHP_function" => "customConvert"],
        'INDI:*:_ASSO'              => ["PHP_function" => "customConvert"],
        
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
        'INDI'                      => ["PHP_function" => "customConvert"],
        'NOTE'                      => ["PHP_function" => "customConvert"],
        'OBJE'                      => ["PHP_function" => "customConvert"],
        'REPO'                      => ["PHP_function" => "customConvert"],
        'SOUR'                      => ["PHP_function" => "customConvert"],
        '_LOC'                      => ["PHP_function" => "customConvert"],

        //Export other records
        '*'							=> [],
    ];

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Remove empty or unlinked records');
    } 

    /**
     * Custom conversion of a Gedcom string
     *
     * @param string        $pattern         The pattern of the filter rule, e. g. INDI:*:DATE
     * @param string        $gedcom          The Gedcom to convert
     * @param array         $records_list    A list with all xrefs and the related records: array <string xref => Record record>
     * @param array<string> $params          Parameters from remote URL requests as well as further parameters, e.g. 'tree' and 'base_url'
     * 
     * @return string                        The converted Gedcom
     */
    public function customConvert(string $pattern, string $gedcom, array &$records_list, array $params = []): string {

        $gedcom = $this->removeEmptyOrUnlinkedRecords($pattern, $gedcom, $records_list, true, true, true, false);
        return $gedcom;
    }   

    /**
    * Analyze a Gedcom record, if it is empty or unlinked (i.e. not referenced) by other records. If yes, return empty Gedcom.
    *
    * @param string $pattern                     The pattern of the filter rule, e. g. INDI:BIRT:DATE
    * @param string $gedcom                      The Gedcom to convert
    * @param array  $records_list                A list with all xrefs and the related records: array <string xref => Record record>
    * @param bool   $remove_empty                Whether empty records shall be removed
    * @param bool   $remove_unlinked             Whether empty records shall be removed
    * @param bool   $remove_references_to_void   Remove references, which point to empty records
    * @param bool   $remove_minimal_individuals  Whether minimal individual records shall be removed
    * 
    * @return string                             The converted Gedcom
    */
    public function removeEmptyOrUnlinkedRecords(
        string  $pattern,
        string  $gedcom,
        array  &$records_list,
        bool    $remove_empty,
        bool    $remove_unlinked,
        bool    $remove_references_to_void,
        bool    $remove_minimal_individuals): string {

        //Remove empty records and records without a reference
        preg_match('/0 @(' . Gedcom::REGEX_XREF  . ')@ (' . Gedcom::REGEX_TAG  . ')/', $gedcom, $match);
        $xref = $match[1] ?? '';

        if ($xref !== '') {
            $record = $records_list[$xref];

            //If record is empty, or not referenced by other records remove Gedcom
            //However, we keep INDI records, which are not referenced
            if (   ($remove_empty && $record->isEmpty()) 
                OR ($pattern !== 'INDI' && $remove_unlinked && !$record->isReferenced())) {

                $gedcom = '';
            }   
            //If record is a minimal individual, reduce Gedcom to empty INDI
            else if ($pattern === 'INDI' && $remove_minimal_individuals && $record->isMinimalIndividual()) {
                $gedcom = "0 @" . $xref . "@ INDI\n";
            }
        }

        //Remove references, which point to empty records
        elseif ($remove_references_to_void && in_array($pattern, [

            'FAM:CHIL',
            'FAM:HUSB',
            'FAM:WIFE',
            'FAM:*:_ASSO',          

            'INDI:ALIA',
            'INDI:ASSO',
            'INDI:*:_ASSO',

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

            preg_match('/[\d] [\w]{4,5} @(' . Gedcom::REGEX_XREF . ')@/', $gedcom, $match);
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
