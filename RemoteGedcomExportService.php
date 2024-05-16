<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2023 webtrees development team
 *                    <http://webtrees.net>

 * DownloadGedcomWithURL (webtrees custom module):
 * Copyright (C) 2023 Markus Hemprich
 *                    <http://www.familienforschung-hemprich.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Header;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Tree;


/**
 * Export data in GEDCOM format
 */
class RemoteGedcomExportService extends GedcomExportService
{
    /**
     * Create a header record for a GEDCOM file, which exports SUBM/SUBN even if no user is logged in
     *
     * @param Tree   $tree
     * @param string $encoding
     * @param bool   $include_sub
     *
     * @return string
     */
    public function createHeader(Tree $tree, string $encoding, bool $include_sub, int $access_level = null): string
    {
        //Take GEDCOM from parent method as a base
        $gedcom = parent::createHeader($tree, $encoding, $include_sub);

        $header = Registry::headerFactory()->make('HEAD', $tree) ?? Registry::headerFactory()->new('HEAD', '0 HEAD', null, $tree);

        if ($header instanceof Header) {

            if ($include_sub) {

                //Apply access level of 'none', because the GEDCOM standard requires to include a submitter and export needs to be consistent if a submitter/submission exists
                //Privacy of the submitter/submission is handled in the submitter/submission object itself
                foreach ($header->facts(['SUBM', 'SUBN'], false, Auth::PRIV_HIDE) as $fact) {

                    //Add submitter/submission if the parent method did not find it, because of access rights
                    if (!str_contains($gedcom, "\n1 " . substr($fact->tag(), -4, 4))) {
                        $gedcom .= "\n" . $fact->gedcom();
                    }
                }
            }
        }

        return $gedcom;
    }

    /**
     * Convert Gedcom record
     *
     * @param string $gedcom
     * @param Tree   $tree
     *
     * @return string
     */
    public function customConvert(string $gedcom, Tree $tree): string
    {   
        $white_list = ExportFilter::WHITE_LIST;
        $converted_gedcom = '';

        //Create temporary record
        preg_match('/^0 @([^@]*)@ (\w+)/', $gedcom, $match);
        $xref = $match[1] ?? 'XREFdummy';
        $record = new TemporaryGedcomRecord($xref, $gedcom, null, $tree);

        //Add Gedcom of record if is in white list
        if (array_key_exists($record->tag(), $white_list)) {

            if (str_starts_with($gedcom, "0 HEAD")) {
                $record_gedcom = "0 HEAD\n";
            }
            elseif (str_starts_with($gedcom, "0 TRLR")) {
                $record_gedcom = "0 TRLR\n";
            }
            else {
                $record_gedcom = $record->createPrivateGedcomRecord(Auth::PRIV_NONE) ."\n";
            }

            $preg_replace_pairs = $white_list[$record->tag()];

            //If regular expressions are provided, run replacements
            foreach ($preg_replace_pairs as $pattern => $replace) {

                $record_gedcom = preg_replace("/" . $pattern . "/", $replace, $record_gedcom);
            }

            $converted_gedcom .= $record_gedcom;
        }
        else {
            return '';
        }

        foreach($record->facts() as $fact) {

            $fact_tag = str_replace($record->tag() . ":", "", $fact->tag());

            if(array_key_exists($record->tag() . ":*", $white_list) OR array_key_exists($fact->tag() . ":*", $white_list)) {

                //Add ALL level Gedcom of fact if is in white list with *
                $fact_gedcom = $fact->gedcom() . "\n";

                if (array_key_exists($record->tag() . ":*", $white_list)) {
                    $preg_replace_pairs = $white_list[$record->tag() . ":*"];
                }
                elseif (array_key_exists($fact->tag() . ":*", $white_list)) {
                    $preg_replace_pairs = $white_list[$fact->tag() . ":*"];
                }
                else {
                    $preg_replace_pairs =[];
                }

                //If regular expressions are provided, run replacements
                foreach ($preg_replace_pairs as $pattern => $replace) {

                    $fact_gedcom = preg_replace("/" . $pattern . "/", $replace, $fact_gedcom);
                } 

                $converted_gedcom .= $fact_gedcom;       
            }
            elseif(array_key_exists($fact->tag(), $white_list)) {

                $fact_value = $fact->value() !== "" ? " " . $fact->value() : "";

                //Add level 1 Gedcom of fact if is in white list
                $converted_gedcom .= "1 ". $fact_tag . $fact_value . "\n";

                //Add level 2 Gedcom of fact if is in white list
                foreach ($white_list as $white_list_tag => $preg_replace_pairs) {

                    if (str_starts_with($white_list_tag, $fact->tag() . ":")) {

                        $level2_tag = str_replace($fact->tag() . ":", "", $white_list_tag);

                        if ($level2_tag !== "") {

                            $level2_fact_value = $fact->attribute($level2_tag);

                            if ($level2_fact_value !== "") {

                                $fact_gedcom = "2 ". $level2_tag . " " . $level2_fact_value . "\n";

                                //If regular expressions are provided, run replacements
                                foreach ($preg_replace_pairs as $pattern => $replace) {

                                    $fact_gedcom = preg_replace("/" . $pattern . "/", $replace, $fact_gedcom);
                                }

                                $converted_gedcom .= $fact_gedcom;
                            }
                        }    
                    } 
                } 
            }
        }

        return $converted_gedcom;
    }
}
