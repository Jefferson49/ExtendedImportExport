<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * An export filter, which handles GEDCOM-L for GEDCOM 7.0
 */
class GEDCOM_L_for_GEDCOM_7_ExportFilter extends AbstractExportFilter implements ExportFilterInterface
{
   protected const EXPORT_FILTER_RULES = [
      
      //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
      //                             ["search pattern" => "replace pattern"],

      'INDI:*:_GODP'             => ["RegExp_macro" => "_GODP_WITN"],

      'FAM:*:_WITN'              => ["RegExp_macro" => "_GODP_WITN"],
      'INDI:*:_WITN'             => ["RegExp_macro" => "_GODP_WITN"],

      'FAM:STAT'                 => ["1 _STAT (NOT|NEVER) MARRIED\n" => "1 NO MARR\n"],
                                      
      'FAM:MARR:TYPE'            => ["2 TYPE RELIGIOUS" => "2 TYPE RELI"],

      '*'                        => [],
   ];

   protected const REGEXP_MACROS = [
		//Name                     => Regular expression to be applied for the chosen GEDCOM tag
		//                                 ["search pattern" => "replace pattern"],
		"_GODP_WITN"			      => ["2 _(GODP|WITN) (.*)" => "2 ASSO @VOID@\n3 PHRASE $2\n3 ROLE $1"],
	];   
}
