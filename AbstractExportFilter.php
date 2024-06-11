<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;

use Throwable;

/**
 * Abstract export filter, which contains basic export filter rules for the mandatory HEAD, SUBM, TRLR structures only
 */
class AbstractExportFilter implements ExportFilterInterface
{
   protected const EXPORT_FILTER = [
      
      //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
      //                             ["search pattern" => "replace pattern"],
      'HEAD'                      => [],
      'HEAD:*'                    => [],

      'SUBM'                      => [],      
      'SUBM:*'                    => [],      

      'TRLR'                      => [],
   ];

    /**
     * Get the export filter
     *
     * @return array
     */
    public function getExportFilter(Tree $tree): array {

      return static::EXPORT_FILTER;
    }

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

        //As a default, return the un-converted Gedcom
        return $gedcom;
    }
    
    /**
     * Validate the export filter
     *
     * @return string   Validation error; empty, if successful validation
     */
    public function validate(): string {

        $name_space = str_replace('\\\\', '\\',__NAMESPACE__ ) .'\\';
        $class_name = str_replace($name_space, '', get_class($this));
        $export_filter_rules = static::EXPORT_FILTER;

        foreach($export_filter_rules as $pattern => $regexps) {

            //Validate tags
            preg_match_all('/!?([A-Z_\*]+)(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*/', $pattern, $match, PREG_PATTERN_ORDER);
            if ($match[0][0] !== $pattern) {
                return I18N::translate('The selected export filter (%s) contains an invalid tag definition', $class_name) . ': ' . $pattern;
            }

            //Validate regular expressions in export filter
            foreach($regexps as $search => $replace) {

                try {
                    preg_match('/' . $search . '/', "Lorem ipsum");
                }
                catch (Throwable $th) {
                    return I18N::translate('The selected export filter (%s) contains an invalid regular expression', $class_name) . ': ' . $search . '. '. $th->getMessage();
                }

                try {
                    preg_replace('/' . $search . '/', $replace, "Lorem ipsum");
                }
                catch (Throwable $th) {
                    return I18N::translate('The selected export filter (%s) contains an invalid regular expression', $class_name) . ': ' . $replace . '. ' . $th->getMessage();
                }
            }

            //Check if black list filter rules have reg exps
            if (strpos($pattern, '!') === 0) {

                foreach($regexps as $search => $replace) {

                    if ($search !== '' OR $replace !== '') {
                        return I18N::translate('The selected export filter (%s) contains a black list filter rule (%s) with a regular expression, which will never be executed, because the black list filter rule will delete the related GEDCOM line.', $class_name, $pattern);
                    } 
                }
            }

            //Check if a rule is dominated by another rule, which is higher priority (i.e. earlier entry in the export filter list)
            $i = 0;
            $size = sizeof(static::EXPORT_FILTER);
            $pattern_list = array_keys(static::EXPORT_FILTER);

            while($i < $size && $pattern !== $pattern_list[$i]) {

                //If tag ends with :* and ist shorter than pattern from list, extend with further :*
                $extended_pattern = $pattern;
                if (substr_count($pattern, ':') < substr_count($pattern_list[$i], ':') && strpos($pattern, '*' , -1)) {

                    while (substr_count($extended_pattern, ':') < substr_count($pattern_list[$i], ':')) $extended_pattern .=':*';
                }

                if (RemoteGedcomExportService::matchTagWithSinglePattern($extended_pattern, $pattern_list[$i])) {

                    return I18N::translate('The filter rule "%s" is dominated by the earlier filter rule "%s" and will never be executed. Please remove the rule or change the order of the filter rules.', $pattern, $pattern_list[$i]);
                }
                $i++;
            }
        }

        return '';
    }       
 
}
