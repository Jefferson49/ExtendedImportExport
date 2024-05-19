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
use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\Factories\AbstractGedcomRecordFactory;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomFilters\GedcomEncodingFilter;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Header;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\ZipArchive\FilesystemZipArchiveProvider;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;
use Throwable;

use function addcslashes;
use function fclose;
use function fopen;
use function fwrite;
use function is_string;
use function preg_match_all;
use function rewind;
use function stream_filter_append;
use function stream_get_meta_data;
use function strlen;
use function tmpfile;

use const PREG_SET_ORDER;
use const STREAM_FILTER_WRITE;

/**
 * Export data in GEDCOM format
 */
class RemoteGedcomExportService extends GedcomExportService
{
    private const ACCESS_LEVELS = [
        'gedadmin' => Auth::PRIV_NONE,
        'user'     => Auth::PRIV_USER,
        'visitor'  => Auth::PRIV_PRIVATE,
        'none'     => Auth::PRIV_HIDE,
    ];

    private ResponseFactoryInterface $response_factory;
    private StreamFactoryInterface $stream_factory;

    public function __construct(ResponseFactoryInterface $response_factory, StreamFactoryInterface $stream_factory)
    {
        $this->response_factory = $response_factory;
        $this->stream_factory   = $stream_factory;
    }

    /**
     * @param Tree                        $tree         Export data from this tree
     * @param bool                        $sort_by_xref Write GEDCOM records in XREF order
     * @param string                      $encoding     Convert from UTF-8 to other encoding
     * @param string                      $privacy      Filter records by role
     * @param string                      $line_endings
     * @param string                      $filename     Name of download file, without an extension
     * @param string                      $format       One of: gedcom, zip, zipmedia, gedzip
     * @param ExportFilterInterface       $export_filter  A GEDCOM export filter
     * @param Collection<int,string|object|GedcomRecord>|null $records
     *
     * @return ResponseInterface
     */
    public function remoteDownloadResponse(
        Tree $tree,
        bool $sort_by_xref,
        string $encoding,
        string $privacy,
        string $line_endings,
        string $filename,
        string $format,
        ExportFilterInterface $export_filter = null,
        Collection $records = null
    ): ResponseInterface {
        $access_level = self::ACCESS_LEVELS[$privacy];

        if ($format === 'gedcom') {
            $resource = $this->remoteExport($tree, $sort_by_xref, $encoding, $access_level, $line_endings, $export_filter, $records);
            $stream   = $this->stream_factory->createStreamFromResource($resource);

            return $this->response_factory->createResponse()
                ->withBody($stream)
                ->withHeader('content-type', 'text/x-gedcom; charset=' . UTF8::NAME)
                ->withHeader('content-disposition', 'attachment; filename="' . addcslashes($filename, '"') . '.ged"');
        }

        // Create a new/empty .ZIP file
        $temp_zip_file  = stream_get_meta_data(tmpfile())['uri'];
        $zip_provider   = new FilesystemZipArchiveProvider($temp_zip_file, 0755);
        $zip_adapter    = new ZipArchiveAdapter($zip_provider);
        $zip_filesystem = new Filesystem($zip_adapter);

        if ($format === 'zipmedia') {
            $media_path = $tree->getPreference('MEDIA_DIRECTORY');
        } elseif ($format === 'gedzip') {
            $media_path = '';
        } else {
            // Don't add media
            $media_path = null;
        }

        $resource = $this->remoteExport($tree, $sort_by_xref, $encoding, $access_level, $line_endings, $export_filter, $records, $zip_filesystem, $media_path);

        if ($format === 'gedzip') {
            $zip_filesystem->writeStream('gedcom.ged', $resource);
            $extension = '.gdz';
        } else {
            $zip_filesystem->writeStream($filename . '.ged', $resource);
            $extension = '.zip';
        }

        fclose($resource);

        $stream = $this->stream_factory->createStreamFromFile($temp_zip_file);

        return $this->response_factory->createResponse()
            ->withBody($stream)
            ->withHeader('content-type', 'application/zip')
            ->withHeader('content-disposition', 'attachment; filename="' . addcslashes($filename, '"') . $extension . '"');
    }

    /**
     * Write GEDCOM data to a stream.
     *
     * @param Tree                                            $tree           Export data from this tree
     * @param bool                                            $sort_by_xref   Write GEDCOM records in XREF order
     * @param string                                          $encoding       Convert from UTF-8 to other encoding
     * @param int                                             $access_level   Apply privacy filtering
     * @param string                                          $line_endings   CRLF or LF
     * @param ExportFilterInterface                           $export_filter  A GEDCOM export filter
     * @param Collection<int,string|object|GedcomRecord>|null $records        Just export these records
     * @param FilesystemOperator|null                         $zip_filesystem Write media files to this filesystem
     * @param string|null                                     $media_path     Location within the zip filesystem
     *
     * @return resource
     */
    public function remoteExport(
        Tree $tree,
        bool $sort_by_xref = false,
        string $encoding = UTF8::NAME,
        int $access_level = Auth::PRIV_HIDE,
        string $line_endings = 'CRLF',
        ExportFilterInterface $export_filter = null,
        ?Collection $records = null,
        ?FilesystemOperator $zip_filesystem = null,
        ?string $media_path = null
    ) {
        $stream = fopen('php://memory', 'wb+');

        if ($stream === false) {
            throw new RuntimeException('Failed to create temporary stream');
        }

        stream_filter_append($stream, GedcomEncodingFilter::class, STREAM_FILTER_WRITE, ['src_encoding' => UTF8::NAME, 'dst_encoding' => $encoding]);

        if ($records instanceof Collection) {
            // Export just these records - e.g. from clippings cart.
            $data = [
                new Collection([$this->createHeader($tree, $encoding, false)]),
                $records,
                new Collection(['0 TRLR']),
            ];
        } elseif ($access_level === Auth::PRIV_HIDE) {
            // If we will be applying privacy filters, then we will need the GEDCOM record objects.
            $data = [
                new Collection([$this->createHeader($tree, $encoding, true)]),
                $this->individualQuery($tree, $sort_by_xref)->cursor(),
                $this->familyQuery($tree, $sort_by_xref)->cursor(),
                $this->sourceQuery($tree, $sort_by_xref)->cursor(),
                $this->otherQuery($tree, $sort_by_xref)->cursor(),
                $this->mediaQuery($tree, $sort_by_xref)->cursor(),
                new Collection(['0 TRLR']),
            ];
        } else {
            // Disable the pending changes before creating GEDCOM records.
            Registry::cache()->array()->remember(AbstractGedcomRecordFactory::class . $tree->id(), static function (): Collection {
                return new Collection();
            });

            $data = [
                new Collection([$this->createHeader($tree, $encoding, true)]),
                $this->individualQuery($tree, $sort_by_xref)->get()->map(Registry::individualFactory()->mapper($tree)),
                $this->familyQuery($tree, $sort_by_xref)->get()->map(Registry::familyFactory()->mapper($tree)),
                $this->sourceQuery($tree, $sort_by_xref)->get()->map(Registry::sourceFactory()->mapper($tree)),
                $this->otherQuery($tree, $sort_by_xref)->get()->map(Registry::gedcomRecordFactory()->mapper($tree)),
                $this->mediaQuery($tree, $sort_by_xref)->get()->map(Registry::mediaFactory()->mapper($tree)),
                new Collection(['0 TRLR']),
            ];
        }

        $media_filesystem = $tree->mediaFilesystem();

        foreach ($data as $rows) {
            foreach ($rows as $datum) {
                if (is_string($datum)) {
                    $gedcom = $datum;
                } elseif ($datum instanceof GedcomRecord) {
                    $gedcom = $datum->privatizeGedcom($access_level);
                } else {
                    $gedcom =
                        $datum->i_gedcom ??
                        $datum->f_gedcom ??
                        $datum->s_gedcom ??
                        $datum->m_gedcom ??
                        $datum->o_gedcom;
                }

                if ($media_path !== null && $zip_filesystem !== null && preg_match('/0 @' . Gedcom::REGEX_XREF . '@ OBJE/', $gedcom) === 1) {
                    preg_match_all('/\n1 FILE (.+)/', $gedcom, $matches, PREG_SET_ORDER);

                    foreach ($matches as $match) {
                        $media_file = $match[1];

                        if ($media_filesystem->fileExists($media_file)) {
                            $zip_filesystem->writeStream($media_path . $media_file, $media_filesystem->readStream($media_file));
                        }
                    }
                }

                $gedcom = $this->wrapLongLines($gedcom, Gedcom::LINE_LENGTH) . "\n";

                //Apply custom conversions according to an export filter
                if ($export_filter !== null) {
                    $gedcom = self::exportFilter($gedcom, $tree, $export_filter);
                }

                if ($line_endings === 'CRLF') {
                    $gedcom = strtr($gedcom, ["\n" => "\r\n"]);
                }

                $bytes_written = fwrite($stream, $gedcom);

                if ($bytes_written !== strlen($gedcom)) {
                    throw new RuntimeException('Unable to write to stream.  Perhaps the disk is full?');
                }
            }
        }

        if (rewind($stream) === false) {
            throw new RuntimeException('Cannot rewind temporary stream');
        }

        return $stream;
    }

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
     * @param Tree $tree
     * @param bool $sort_by_xref
     *
     * @return Builder
     */
    private function familyQuery(Tree $tree, bool $sort_by_xref): Builder
    {
        $query = DB::table('families')
            ->where('f_file', '=', $tree->id())
            ->select(['f_gedcom', 'f_id']);

        if ($sort_by_xref) {
            $query
                ->orderBy(new Expression('LENGTH(f_id)'))
                ->orderBy('f_id');
        }

        return $query;
    }

    /**
     * @param Tree $tree
     * @param bool $sort_by_xref
     *
     * @return Builder
     */
    private function individualQuery(Tree $tree, bool $sort_by_xref): Builder
    {
        $query = DB::table('individuals')
            ->where('i_file', '=', $tree->id())
            ->select(['i_gedcom', 'i_id']);

        if ($sort_by_xref) {
            $query
                ->orderBy(new Expression('LENGTH(i_id)'))
                ->orderBy('i_id');
        }

        return $query;
    }

    /**
     * @param Tree $tree
     * @param bool $sort_by_xref
     *
     * @return Builder
     */
    private function sourceQuery(Tree $tree, bool $sort_by_xref): Builder
    {
        $query = DB::table('sources')
            ->where('s_file', '=', $tree->id())
            ->select(['s_gedcom', 's_id']);

        if ($sort_by_xref) {
            $query
                ->orderBy(new Expression('LENGTH(s_id)'))
                ->orderBy('s_id');
        }

        return $query;
    }

    /**
     * @param Tree $tree
     * @param bool $sort_by_xref
     *
     * @return Builder
     */
    private function mediaQuery(Tree $tree, bool $sort_by_xref): Builder
    {
        $query = DB::table('media')
            ->where('m_file', '=', $tree->id())
            ->select(['m_gedcom', 'm_id']);

        if ($sort_by_xref) {
            $query
                ->orderBy(new Expression('LENGTH(m_id)'))
                ->orderBy('m_id');
        }

        return $query;
    }

    /**
     * @param Tree $tree
     * @param bool $sort_by_xref
     *
     * @return Builder
     */
    private function otherQuery(Tree $tree, bool $sort_by_xref): Builder
    {
        $query = DB::table('other')
            ->where('o_file', '=', $tree->id())
            ->whereNotIn('o_type', [Header::RECORD_TYPE, 'TRLR'])
            ->select(['o_gedcom', 'o_id']);

        if ($sort_by_xref) {
            $query
                ->orderBy('o_type')
                ->orderBy(new Expression('LENGTH(o_id)'))
                ->orderBy('o_id');
        }

        return $query;
    }

    /**
     * Convert Gedcom record according to an export filter
     *
     * @param string                $gedcom
     * @param Tree                  $tree
     * @param ExportFilterInterface $export_filter
     *
     * @return string
     */
    public static function exportFilter(string $gedcom, Tree $tree, ExportFilterInterface $export_filter): string
    {   
        if ($export_filter === null) return $gedcom;

        $export_filter_list = $export_filter->getExportFilter();
        $export_filter_patterns = array_keys($export_filter_list);
        $converted_gedcom = '';

        //Create temporary record
        preg_match('/^0 @([^@]*)@ (\w+)/', $gedcom, $match);
        $xref = $match[1] ?? 'XREFdummy';
        $record = new TemporaryGedcomRecord($xref, $gedcom, null, $tree);

        //Add Gedcom of record if is in white list
        $matched_tag_pattern = self::matchedPattern($record->tag(), $export_filter_patterns);

        if ($matched_tag_pattern !== '') {

            if (str_starts_with($gedcom, "0 HEAD")) {
                $record_gedcom = "0 HEAD\n";
            }
            elseif (str_starts_with($gedcom, "0 TRLR")) {
                $record_gedcom = "0 TRLR\n";
            }
            else {
                $record_gedcom = $record->createPrivateGedcomRecord(Auth::PRIV_NONE) ."\n";
            }

            //If regular expressions are provided, run replacements
            $record_gedcom = self::preg_replace_for_array_of_pairs($export_filter_list[$matched_tag_pattern], $record_gedcom);

            $converted_gedcom .= $record_gedcom;
        }
        else {
            return '';
        }

        foreach($record->facts() as $fact) {

            $fact_tag = str_replace($record->tag() . ":", "", $fact->tag());
            $matched_tag_pattern = self::matchedPattern($fact->tag(), $export_filter_patterns);

            if ($matched_tag_pattern !== '') {

                $fact_value = $fact->value() !== "" ? " " . $fact->value() : "";

                //Add level 1 Gedcom of fact
                $fact_gedcom = "1 ". $fact_tag . $fact_value . "\n";

                //If regular expressions are provided, run replacements
                $fact_gedcom = self::preg_replace_for_array_of_pairs($export_filter_list[$matched_tag_pattern], $fact_gedcom);

                $converted_gedcom .= $fact_gedcom;

                //Get all level 2 tags and values
                foreach (self::getNextLevelTagValues($fact->tag(), $fact->gedcom(), 1 ) as $level2_tag => $level2_value) {

                    $matched_tag_pattern = self::matchedPattern($fact->tag()  . ":" . $level2_tag, $export_filter_patterns);

                    if ($matched_tag_pattern !== '') {

                        //Add level 2 Gedcom of fact
                        $level2_gedcom = "2 ". $level2_tag . " " . $level2_value . "\n";

                        //If regular expressions are provided, run replacements
                        $level2_gedcom = self::preg_replace_for_array_of_pairs($export_filter_list[$matched_tag_pattern], $level2_gedcom);

                        $converted_gedcom .= $level2_gedcom;
                    }
                } 
            }
        }

        return $converted_gedcom;
    }

    /**
     * Match a given tag (e.g. FAM:MARR:DATE) with a list of tag patterns (e.g. [INDI:BIRT, FAM:*:DATE])
     *
     * @param string     $tag                   e.g. FAM:MARR:DATE
     * @param array      $patterns              e.g. [INDI:BIRT, FAM:*:DATE]
     *
     * @return string    Matched pattern; empty if no match
     */
    public static function matchedPattern(string $tag, array $patterns): string
    {  
        //Match whether is found as black listed
        $check_as_white_list = false;
        $i = 0;
        $match = false;

        while ($i < sizeof($patterns) && !$match) {
            $match = self::matchTagWithSinglePattern($tag, $patterns[$i], $check_as_white_list);
            $i++;
        }

        //If black list match was found return false
        if ($match) return '';

        //Match whether is found as white listed
        $check_as_white_list = true;
        $i = 0;
        $match = false;

        while ($i < sizeof($patterns) && !$match) {
            $match = self::matchTagWithSinglePattern($tag, $patterns[$i], $check_as_white_list);
            $i++;
        }

        //Return result of white list check
        if ($match) {
            return $patterns[$i-1];
        }
        
        return '';
    }

    /**
     * Match a given tag (e.g. FAM:MARR:DATE) with a tag pattern (e.g. FAM:*:DATE)
     *
     * @param string     $tag                   e.g. FAM:MARR:DATE
     * @param string     $pattern               e.g. FAM:*:DATE
     * @param bool       $check_as_white_list   whether patterns are checked as white list; otherwise as black list
     *
     * @return bool      Whether the tag could be matched or not      
     */
    public static function matchTagWithSinglePattern(string $tag, string $pattern, bool $check_as_white_list = true): bool
    {   
        $is_white_list_pattern = true;

        if (str_starts_with($pattern, '!')) {

            if ($check_as_white_list) return false;
            $is_white_list_pattern = false;
            $pattern = substr($pattern, 1);
        }

        if (str_ends_with($pattern, ':*')) $pattern .= ':*:*:*:*:*:*:*:*:*:*';

        preg_match_all('/([^:]+)(:[^:]+)*(:[^:]+)*(:[^:]+)*(:[^:]+)*(:[^:]+)*(:[^:]+)*(:[^:]+)*(:[^:]+)*(:[^:]+)*/', $tag, $tag_tokens, PREG_PATTERN_ORDER);
        preg_match_all('/([^:]+)(:[^:]+)*(:[^:]+)*(:[^:]+)*(:[^:]+)*(:[^:]+)*(:[^:]+)*(:[^:]+)*(:[^:]+)*(:[^:]+)*/', $pattern, $pattern_tokens, PREG_PATTERN_ORDER);

        //Return false if nothing was found
        if ($tag_tokens[0] === '' OR $pattern_tokens[0] === '') return false;

        $i = 1;
        $passed_last_token = false;
        $match = true;

        while ($i < sizeof($tag_tokens) && !$passed_last_token && $match) {

            if ($pattern_tokens[$i][0] !== ':*' && $pattern_tokens[$i][0] !== $tag_tokens[$i][0]) $match = false;
            $passed_last_token = $tag_tokens[$i][0] === "";
            $i++;
        }

        return ($is_white_list_pattern && $check_as_white_list && $match) OR (!$is_white_list_pattern && !$check_as_white_list && $match);
    }

    /**
     * Get all next level tags and values
     *
     * @param string $tag       Tag of the current level
     * @param string $gedcom    GEDCOM under the current level tag
     * @param string $level     Current level
     *
     * @return array            An array with all next level tags and their values
     */
    public static function getNextLevelTagValues(string $tag, string $gedcom, int $level): array
    {
        $next_level_tag_values = [];
        preg_match_all("/\n" . $level + 1 . " ([^\n ]+) [^\n]+\n/", $gedcom, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $next_level_tag = $match[1];

            if (preg_match('/\n' . $level + 1 . ' ' . $next_level_tag . '\b ?(.*(?:(?:\n' . $level + 1 . ' CONT ?.*)*)*)/', $gedcom, $match)) {
                $value = preg_replace("/\n' . $level + 1 . ' CONT ?/", "\n", $match[1]);
    
                $next_level_tag_values[$next_level_tag] = Registry::elementFactory()->make($tag . ':' . $next_level_tag)->canonical($value);
            }                

        }

        return $next_level_tag_values;
    }      

    /**
     * Preg_replace with an array of replace pairs
     *
     * @param array $preg_replace_pairs       An array with replace pairs, without '/search/'
     * @param string $subject                 Text
     *
     * @return string                  
     */
    public static function preg_replace_for_array_of_pairs(array $preg_replace_pairs, string $subject): string {

        //If regular expressions are provided, run replacements
        foreach ($preg_replace_pairs as $pattern => $replace) {

            $subject = preg_replace("/" . $pattern . "/", $replace, $subject);
        }

        return $subject;
    }
}
