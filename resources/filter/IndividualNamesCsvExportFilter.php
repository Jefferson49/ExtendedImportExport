<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * Tag definitions and regular expressions for export filter
 */
class IndividualNamesCsvExportFilter implements ExportFilterInterface
{
    use ExportFilterTrait;
    public const EXPORT_FILTER = [
      
      //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
      //                             ["search pattern" => "replace pattern"],
      'HEAD'                      => [".*\n" => "Surname,Given names\n"],

      'INDI'                      => ["0 @([^@].+)@ INDI\n" => ""],
      'INDI:NAME'                 => ["1 NAME (.*[^ ])? ?\/(.*)\/" => "\"$2\",\"$1\""],
   ];
}
