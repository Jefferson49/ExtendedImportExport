<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\I18N;

/**
 * A GEDCOM filter to remove void references
 */
class RemoveVoidReferencesGedcomFilter extends AbstractGedcomFilter
{
    protected const USES_REFERENCES_ANALYSIS = true;
    protected const GEDCOM_FILTER_RULES = [
        
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],


        //Check sub structures of records for void references
        '*:NOTE'                    => ["PHP_function" => "customConvert"],
        '*:*:NOTE'                  => ["PHP_function" => "customConvert"],
        '*:*:*:NOTE'                => ["PHP_function" => "customConvert"],

        '*:OBJE'                    => ["PHP_function" => "customConvert"],
        '*:*:OBJE'                  => ["PHP_function" => "customConvert"],
        '*:*:*:OBJE'                => ["PHP_function" => "customConvert"],
        '*:*:*:*:OBJE'              => ["PHP_function" => "customConvert"],

        '*:SOUR'                    => ["PHP_function" => "customConvert"],
        '*:*:SOUR'                  => ["PHP_function" => "customConvert"],
        '*:*:*:SOUR'                => ["PHP_function" => "customConvert"],

        'HEAD:*'                    => ["PHP_function" => "customConvert"],
        
        'FAM:*:_ASSO'               => ["PHP_function" => "customConvert"],        
        'FAM:*'                     => ["PHP_function" => "customConvert"],

        'INDI:*:_ASSO'              => ["PHP_function" => "customConvert"],
        'INDI:*'                    => ["PHP_function" => "customConvert"],
        
        'SOUR:REPO'                 => ["PHP_function" => "customConvert"],

        //Export all records
        '*'							=> [],
    ];

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Remove void references');
    } 

    /**
     * Custom conversion of a Gedcom string
     *
     * @param string        $pattern         The pattern of the filter rule, e. g. INDI:*:DATE
     * @param string        $gedcom          The Gedcom to convert
     * @param array         $records_list    A list with all xrefs and the related records: array <string xref => Record record>
     *                                       Records offer methods to be checked whether they are empty, referenced, etc.
     * @param array<string> $params          Parameters from remote URL requests as well as further parameters, e.g. 'tree' and 'base_url'
     * 
     * @return string                        The converted Gedcom
     */
    public function customConvert(string $pattern, string $gedcom, array &$records_list, array $params = []): string {

        preg_match('/^[\d] ' . Gedcom::REGEX_TAG  . ' @(' . Gedcom::REGEX_XREF  . ')@.*/', $gedcom, $match);

        $xref   = $match[1] ?? '';
        $record = $records_list[$xref] ?? null;

        //If a reference was found and the record related to the XREF exists forward the GEDCOM data.
        if (sizeof($match) === 0 OR $xref === '' OR $record === null OR $record->exists()) {
            return $gedcom;
        };

        //Otherwise remove GEDCOM data, because the XREF is assumed to be a void reference
        return '';
    }
}
