<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tree;


/**
 * A GEDCOM filter, which replaces XREFs in notes and text
 */
class ReplaceXrefsInNotesAndText extends AbstractGedcomFilter
{
    private Tree $tree;

    protected const GEDCOM_FILTER_RULES = [
      
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //You might want to insert some filter rules here, e.g. to remove or convert certain tag structures

        'NOTE'                      => ["PHP_function" => "customConvert"],
        'NOTE:*'                    => ["PHP_function" => "customConvert"],
        '*:NOTE'                    => ["PHP_function" => "customConvert"],
        '*:NOTE:*'                  => ["PHP_function" => "customConvert"],
        '*:*:NOTE'                  => ["PHP_function" => "customConvert"],
        '*:*:NOTE:*'                => ["PHP_function" => "customConvert"],
        '*:*:*:NOTE'                => ["PHP_function" => "customConvert"],
        '*:*:*:NOTE:*'              => ["PHP_function" => "customConvert"],
        '*:*:*:*:NOTE'              => ["PHP_function" => "customConvert"],
        '*:*:*:*:NOTE:*'            => ["PHP_function" => "customConvert"],
        '*:TEXT'                    => ["PHP_function" => "customConvert"],
        '*:TEXT:*'                  => ["PHP_function" => "customConvert"],
        '*:*:TEXT'                  => ["PHP_function" => "customConvert"],
        '*:*:TEXT:*'                => ["PHP_function" => "customConvert"],
        '*:*:*:TEXT'                => ["PHP_function" => "customConvert"],
        '*:*:*:TEXT:*'              => ["PHP_function" => "customConvert"],
        '*:*:*:*:TEXT'              => ["PHP_function" => "customConvert"],
        '*:*:*:*:TEXT:*'            => ["PHP_function" => "customConvert"],

        //Export other structures
        '*'                         => [],
	];

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Replace XREFs by names in notes and text');
    }

    /**
     * Get the Gedcom filter rules
     * 
     * @param array<string> $params   Parameters from remote URL requests 
     *                                as well as further parameters, e.g. 'tree' and 'base_url'
     *
     * @return array
     */
    public function getGedcomFilterRules(array $params = []): array {

        $tree_name = $params['tree_name'] ?? '';

        if($tree_name !== '') {
            $tree_service = new TreeService(new GedcomImportService());
            $all_trees = $tree_service->all();
            $this->tree = $all_trees[$tree_name];
        }

        return parent::getGedcomFilterRules($params);
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

        if(     preg_match("/[\d] " . Gedcom::REGEX_TAG . " @" . Gedcom::REGEX_XREF . "@\n/", $gedcom) 
            OR  preg_match("/[\d] @" . Gedcom::REGEX_XREF . "@ " . Gedcom::REGEX_TAG . "\n/", $gedcom)
            ) {
            return $gedcom;
        }

        if (!preg_match_all('/@(' . Gedcom::REGEX_XREF . ')@/', $gedcom, $matches, PREG_PATTERN_ORDER)) {
            return $gedcom;
        }

        foreach($matches[1] as $xref) {

            $records = [
                Registry::familyFactory()->make($xref, $this->tree),
                Registry::individualFactory()->make($xref, $this->tree),
                Registry::mediaFactory()->make($xref, $this->tree),
                Registry::noteFactory()->make($xref, $this->tree),
                Registry::repositoryFactory()->make($xref, $this->tree),
                Registry::sourceFactory()->make($xref, $this->tree),
                Registry::submitterFactory()->make($xref, $this->tree),
                Registry::submissionFactory()->make($xref, $this->tree),
                Registry::locationFactory()->make($xref, $this->tree),
            ];

            foreach($records as $record) {
                if($record !== null) {
                    $primary_name = $record->getPrimaryName();
                    $name = $record->getAllNames()[$primary_name];
                    if(isset($name['fullNN'])) {
                        $gedcom = str_replace( '@' . $xref . '@', $name['fullNN'], $gedcom);
                    }
                    elseif ($record::RECORD_TYPE === Family::RECORD_TYPE && isset($name['sort'])) {
                        $replace = str_replace(',', ', ', $name['sort']);
                        $gedcom = str_replace( '@' . $xref . '@', $replace, $gedcom);
                    }
                }
            }
        }

        return $gedcom;
    }
}
