<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2024 webtrees development team
 *                    <http://webtrees.net>
 *
 * Fancy Research Links (webtrees custom module):
 * Copyright (C) 2022 Carmen Just
 *                    <https://justcarmen.nl>
 *
 * ExtendedImportExport (webtrees custom module):
 * Copyright (C) 2024 Markus Hemprich
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
 *
 * 
 * ExtendedImportExport
 *
 * A weebtrees(https://webtrees.net) 2.1 custom module for advanced GEDCOM import, export
 * and filter operations. The module also supports remote downloads/uploads via URL requests.
 * 
 */

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\Factories\AbstractGedcomRecordFactory;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomFilters\GedcomEncodingFilter;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Header;
use Fisharebest\Webtrees\I18N;
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
use function explode;
use function fclose;
use function fopen;
use function fwrite;
use function is_string;
use function preg_match_all;
use function preg_replace;
use function rewind;
use function stream_filter_append;
use function stream_get_meta_data;
use function strlen;
use function strpos;
use function tmpfile;

use const PREG_SET_ORDER;
use const STREAM_FILTER_WRITE;

/**
 * Export data in GEDCOM format
 */
class FilteredGedcomExportService extends GedcomExportService
{
    //Custom tags and schema definitions
    private const SCHEMAS = [

        //Collection of known custom tags
        //Source: https://wiki.genealogy.net/GEDCOM/_Nutzerdef-Tag#Tabelle_1
        ['https://wiki.genealogy.net/GEDCOM/_Nutzerdef-Tag#Tabelle_1' =>
            [
                '_ABBR', '_ADPF', '_ADPM', '_ADPN', '_AHNNR', '_AIDN', '_AKA', '_AKAN', '_ALIA', '_ALTPATH', '_AON', '_APID', '_ASSO', '_AUTO', '_BIRN', 
                '_BRTM', '_BKM', '_BUCH', '_BUERGERORT', '_CALL', '_CDATE', '_CENN', '_CIRC', '_COML', '_CONF_FLAG', '_COR', '_CORR', '_CRE', '_CREAT',
                '_Creat', '_CTYP', '_CURN', '_CUTOUT', '_DATE', '_DATE_TYPE', '_DATE2', '_DCAUSE', '_DEFN', '_DEG', '_DEP', '_DETS', '_DIVERSES', '_DMGD',
                '_DNA', '_ELEC', '_EMAIL', '_EMPLOY', '_EVENT_DEFN', '_EVID', '_EVN', '_EXCM', '_EXPORTED_FROM_SITE_ID', '_EYEC', '_EYES', '_FARN', '_FA1',
                '_FCTRY', '_FID', '_FILESIZE', '_FKAN', '_FNRL', '_FOKOID', '_FOOT', '_FPOST', '_FREL', '_FRKA', '_FSFTID', '_FSTAE', '_FUN', '_GERN',
                '_GODF', '_GODP', '_GODT', '_GOV', '_GOVTYPE', '_GRUPPE', '_HAIR', '_HEBN', '_HEIG', '_HEIM', '_HEIRATNAME', '_HME', '_HNM', '_HOL', '_HOME',
                '_HUSB', '_IMPF', '_INDG', '_INDN', '_INET', '_INFO', '_INTE', '_ITALIC', '_JAG', '_JUST', '_KTIT', '_LAD ', '_LAM ', '_LAS ', '_LAN', '_LEBENSORT',
                '_LINK', '_LIV', '_LNCH', '_LOC', '_LOD ', '_LOM ', '_LOS ', '_LON', '_MAIDENHEAD', '_MARI', '_MARN', '_MARNM', '_MARR', '_MARRNAME', 
                '_MARRNAMEHUSB', '_MARRNAMEWIFE', '_MASTER', '_MBON', '_MDCL', '_MEDC', '_MEDI', '_MEND', '_MHRM', '_MHSM', '_MHAV', '_MILI', '_MILT', '_MILTID',
                '_MISN', '_MREL', '_MREL', '_MSTAT', '_NAM', '_NAMC', '_NAME', '_NAMM', '_NAMS', '_NAMW', '_NAVI', '_NAVM', '_NCHI', '_NEW', '_NLIV', '_NMAR',
                '_NMR', '_NONE', '_NONE', '_NOTH', '_NR', '_ORGSOUR', '_ORI', '_OTHN', '_OVER', '_PAREN', '_PEI', '_PERC', '_PHOM', '_PHOTO', '_PHOTO_RIN',
                '_PLAC', '_PLAC_DEFN', '_PLACE_TYPE', '_PLACE', '_PLACTODAY', '_PMOB', '_POSITION', '_POST', '_POST', '_PREF', '_PREP', '_PRI', '_PRIM',
                '_PRIM', '_PRIMARY', '_PRIM_CUTOUT', '_PRIO', '_PRIV', '_PRMN', '_PROJECT_GUID', '_QUAL', '_QUAY', '_QUOTED', '_RDATE', '_REC', '_REL', '_RELN',
                '_RINS', '_RTLSAVE', '_RUFNAME', '_RUID', '_SCBK', '_SCHA', '_SCHEMA', '_SDATE', '_SENDOF', '_SENDOM', '_SENDOU', '_SENDPF', '_SENDPM', '_SENDPU',
                '_SENF', '_SENM', '_SENPOF', '_SENPOM', '_SENPOU', '_SENU', '_SEPR', '_SHON', '_SIC', '_SIGN', '_SLDN', '_SM_MERGES', '_SOUND', '_SOUR', '_SSHOW',
                '_STAT', '_STP', '_STYLE', '_SUBM', '_SURN', '_TASK', '_TODO', '_TXT', '_TYPE', '_TYPE', '_UNKN', '_UPD', '_URL', '_URKU', '_VERI',
                '_WEIG', '_WGFM', '_WIFE', '_WITN', '_WT_USER', '_WT_OBJE_SORT', '_WTN', '_YART', '_ZUS', '_ZVST',
            ],
        ],

        //webtrees
        ['https://www.webtrees.net/' =>
            [
                '_WT_USER',
            ],
        ],        
    ];

    //GEDCOM-L custom tags and schema definitions
    private const GEDCOM_L_SCHEMAS = [
        
        //GEDCOM-L Addendum, R2
        ['https://genealogy.net/GEDCOM/' =>
            [
                '_ASSO', '_CAT', '_CDATE', '_GODP', '_GOV', '_GOVTYPE', '_LOC', '_NAME', '_POST', '_PRIM', '_RDATE', '_RUFNAME', '_SCHEMA', '_STAT', '_TODO',
                '_WITN',
            ],
        ],
    ];

    public const ACCESS_LEVELS = [
        'gedadmin' => Auth::PRIV_NONE,
        'user'     => Auth::PRIV_USER,
        'visitor'  => Auth::PRIV_PRIVATE,
        'none'     => Auth::PRIV_HIDE,
    ];

    private ResponseFactoryInterface $response_factory;

    private StreamFactoryInterface $stream_factory;

    //List of custom tags, which were found in the GEDCOM data
    private array $custom_tags_found;

    //A flag indicating whether to apply Gedcom 7 SCHMA tag analysis to the final export
    private bool $use_schema_tag_analysis;

    //A flag indicating whether CONC structures shall be split without leading and trailing spaces
    private bool $wrap_lines_without_leading_and_trailing_spaces;


    /**
     * @param ResponseFactoryInterface $response_factory
     * @param StreamFactoryInterface   $stream_factory
     */
	public function __construct(ResponseFactoryInterface $response_factory, StreamFactoryInterface $stream_factory)
	{
		$this->response_factory = $response_factory;
		$this->stream_factory   = $stream_factory;
        $this->records_xref_list = [];
        $this->empty_records_xref_list = [];
        $this->references_list = [];
        $this->custom_tags_found = [];
        $this->use_schema_tag_analysis = false;      
        $this->wrap_lines_without_leading_and_trailing_spaces = false;  
	}

    /**
     * @param Tree                         $tree           Export data from this tree
     * @param bool                         $sort_by_xref   Write GEDCOM records in XREF order
     * @param string                       $encoding       Convert from UTF-8 to other encoding
     * @param string                       $privacy        Filter records by role
     * @param string                       $line_endings   CRLF or LF
     * @param string                       $filename       Name of download file, without an extension
     * @param string                       $format         One of: gedcom, zip, zipmedia, gedzip
     * @param array<GedcomFilterInterface> $gedcom_filters An array, which contains GEDCOM filters
     * @param array<string>                $params         Parameters from remote URL requests as well as further parameters, e.g. 'tree' and 'base_url'
     * @param Collection<int,string|object|GedcomRecord>|null $records
     * @param bool                         $head_and_trlr  Whether to add HEAD and TRLR if just a collection of records is exported     
     * @param FilesystemOperator|null      $zip_filesystem Write media files to this filesystem
     * @param string|null                  $media_path     Location within the zip filesystem
     *
     * @return resource
     */
    public function filteredResource(
        Tree $tree = null,
        bool $sort_by_xref,
        string $encoding,
        string $privacy,
        string $line_endings,
        string $filename,
        string $format,
        array $gedcom_filters = null,
        array $params = [],
        ?Collection $records = null,
        ?bool $head_and_trlr = false,        
        ?FilesystemOperator $zip_filesystem = null,
        ?string $media_path = null        
    ) {
        $access_level = self::ACCESS_LEVELS[$privacy];

        if ($format === 'gedcom' OR $format === 'other') {
            //Create export
            return $this->filteredExport($tree, $sort_by_xref, $encoding, $access_level, $line_endings, $gedcom_filters, $params, $records, $head_and_trlr);
        }

        // Create a new/empty .ZIP file
        $temp_zip_file  = stream_get_meta_data(tmpfile())['uri'];
        $zip_provider   = new FilesystemZipArchiveProvider($temp_zip_file, 0755);
        $zip_adapter    = new ZipArchiveAdapter($zip_provider);
        $zip_filesystem = new Filesystem($zip_adapter);

        if ($tree !== null && $format === 'zipmedia') {
            $media_path = $tree->getPreference('MEDIA_DIRECTORY');
        } elseif ($format === 'gedzip') {
            $media_path = '';
        } else {
            // Don't add media
            $media_path = null;
        }

        //Create export
        $resource = $this->filteredExport($tree, $sort_by_xref, $encoding, $access_level, $line_endings, $gedcom_filters, $params, $records, $head_and_trlr, $zip_filesystem, $media_path);

        if ($format === 'gedzip') {
            $zip_filesystem->writeStream('gedcom.ged', $resource);
        } else {
            $zip_filesystem->writeStream($filename . '.ged', $resource);
        }

        fclose($resource);

        return fopen($temp_zip_file, 'r');
    }
    
    /**
     * @param Tree                         $tree           Export data from this tree
     * @param bool                         $sort_by_xref   Write GEDCOM records in XREF order
     * @param string                       $encoding       Convert from UTF-8 to other encoding
     * @param string                       $privacy        Filter records by role
     * @param string                       $line_endings   CRLF or LF
     * @param string                       $filename       Name of download file, without an extension
     * @param string                       $format         One of: gedcom, zip, zipmedia, gedzip
     * @param array<GedcomFilterInterface> $gedcom_filters An array, which contains GEDCOM filters
     * @param array<string>                $params         Parameters from remote URL requests as well as further parameters, e.g. 'tree' and 'base_url'
     * @param Collection|null              $records        Just export these records
     * @param bool                         $head_and_trlr  Whether to add HEAD and TRLR if just a collection of records is exported     
     * @param FilesystemOperator|null      $zip_filesystem Write media files to this filesystem
     * @param string|null                  $media_path     Location within the zip filesystem     
     *
     * @return ResponseInterface
     */
    public function filteredDownloadResponse(
        Tree $tree = null,
        bool $sort_by_xref,
        string $encoding,
        string $privacy,
        string $line_endings,
        string $filename,
        string $extension,
        string $format,
        array $gedcom_filters = null,
        array $params = [],
        ?Collection $records = null,
        ?bool $head_and_trlr = false,        
        ?FilesystemOperator $zip_filesystem = null,
        ?string $media_path = null        
    ): ResponseInterface {

        if ($format === 'gedcom' OR $format === 'other') {
            //Create export
            $resource = $this->filteredResource($tree, $sort_by_xref, $encoding, $privacy, $line_endings, $filename, $format, $gedcom_filters, $params, $records, $head_and_trlr);
            $stream = $this->stream_factory->createStreamFromResource($resource);

            return $this->response_factory->createResponse()
                ->withBody($stream)
                ->withHeader('content-type', 'text/x-gedcom; charset=' . UTF8::NAME)
                ->withHeader('content-disposition', 'attachment; filename="' . addcslashes($filename, '"') . $extension .'"');
        }
        
        //Create export
        $resource = $this->filteredResource($tree, $sort_by_xref, $encoding, $privacy, $line_endings, $filename, $format, $gedcom_filters, $params, $records, $head_and_trlr, $zip_filesystem, $media_path);
        $stream = $this->stream_factory->createStreamFromResource($resource);

        if ($format === 'gedzip') {
            $extension = '.gdz';
        } else {
            $extension = '.zip';
        }

        return $this->response_factory->createResponse()
        ->withBody($stream)
        ->withHeader('content-type', 'application/zip')
        ->withHeader('content-disposition', 'attachment; filename="' . addcslashes($filename, '"') . $extension . '"');
    }

    /**
     * Filter GEDCOM data and write to a stream.
     *
     * @param Tree                                            $tree           Export data from this tree
     * @param bool                                            $sort_by_xref   Write GEDCOM records in XREF order
     * @param string                                          $encoding       Convert from UTF-8 to other encoding
     * @param int                                             $access_level   Apply privacy filtering
     * @param string                                          $line_endings   CRLF or LF
     * @param array<GedcomFilterInterface>                    $gedcom_filters An array, which contains GEDCOM filters
     * @param array<string>                                   $params         Parameters from remote URL requests as well as further parameters, e.g. 'tree' and 'base_url'
     * @param Collection<int,string|object|GedcomRecord>|null $records        Just export these records
     * @param bool                                            $head_and_trlr  Whether to add HEAD and TRLR if just a collection of records is exported     
     * @param FilesystemOperator|null                         $zip_filesystem Write media files to this filesystem
     * @param string|null                                     $media_path     Location within the zip filesystem
     * 
     * @return resource
     */
    public function filteredExport(
        Tree $tree = null,
        bool $sort_by_xref = false,
        string $encoding = UTF8::NAME,
        int $access_level = Auth::PRIV_HIDE,
        string $line_endings = 'CRLF',
        array $gedcom_filters = [],
        array $params = [],
        ?Collection $records = null,
        ?bool $head_and_trlr = false,
        ?FilesystemOperator $zip_filesystem = null,
        ?string $media_path = null
    ) {
        //Create stream and initialize array with Gedcom export
        $stream = fopen('php://memory', 'wb+');

        //Initialize array for Gedcom export
        $gedcom_export = [];

        if ($stream === false) {
            throw new RuntimeException('Failed to create temporary stream');
        }

        stream_filter_append($stream, GedcomEncodingFilter::class, STREAM_FILTER_WRITE, ['src_encoding' => UTF8::NAME, 'dst_encoding' => $encoding]);

        if ($records instanceof Collection) {
            // Export just these records - e.g. from clippings cart.
            if ($head_and_trlr) {
                $data = [
                    new Collection([$this->createHeader($tree, $encoding, false)]),
                    $records,
                    new Collection(['0 TRLR']),
                    ];    
            } else {
                $data = [
                    $records,
                ];    
            }
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

        if ($tree !== null) {
            $media_filesystem = $tree->mediaFilesystem();
        }

        foreach ($data as $rows) {
            foreach ($rows as $datum) {
                if (is_string($datum)) {
                    $gedcom = $datum;
                } elseif ($datum instanceof GedcomRecord) {
                    $gedcom = $datum->privatizeGedcom($access_level);

                    if ($gedcom === '') {
                        continue;
                    }
                } else {
                    $gedcom =
                        $datum->i_gedcom ??
                        $datum->f_gedcom ??
                        $datum->s_gedcom ??
                        $datum->m_gedcom ??
                        $datum->o_gedcom;
                }

                //Add line end if not already exists
                if (!strpos($gedcom, "\n", -1)) {
                    $gedcom .= "\n";
                }

                //Add Gedcom to the export
                $gedcom_export[] = $gedcom;
			}
        }

        //Initialize matched tag combinations. 
        $matched_tag_combinations = [];
        //After filter application, the array keys will contain all tag combinations from the final filter run
        //array: tag combination => matched filter rule

        //Apply GEDCOM filters
        $gedcom_export = $this->applyGedcomFilters($gedcom_export, $gedcom_filters, $matched_tag_combinations, $params);

        //Assume GEDCOM 7 export, if first item in record list is a GEDCOM 7 header
        $gedcom7 = ($this->isGedcom7Header($gedcom_export[0] ?? false));

        // If requested, add SCHMA for extension tags found
        if ($gedcom7 && $this->use_schema_tag_analysis) {

            $this->addSchema($gedcom_export, $matched_tag_combinations);
        }

        //Add a byte order mark for UTF8 if Gedcom 7 (required by the standard) 
        if ($gedcom7 && $encoding === UTF8::NAME) {

            $byte_order_mark = UTF8::BYTE_ORDER_MARK;
            $bytes_written = fwrite($stream, $byte_order_mark);
        }

        //Finally, write Gedcom data to stream
        foreach($gedcom_export as $gedcom) {

            //Add media files to ZIP file
            if ($media_path !== null && $zip_filesystem !== null && preg_match('/0 @' . Gedcom::REGEX_XREF . '@ OBJE/', $gedcom) === 1) {
                preg_match_all('/\n1 FILE (.+)/', $gedcom, $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    $media_file = $match[1];

                    if ($tree !== null) {
                        if ($media_filesystem->fileExists($media_file)) {
                            $zip_filesystem->writeStream($media_path . $media_file, $media_filesystem->readStream($media_file));
                        }    
                    }
                }
            }

            //If not Gedcom 7, wrap long lines
            if (!$gedcom7) {

                if ($this->wrap_lines_without_leading_and_trailing_spaces) {
                    $gedcom = $this->wrapLongLinesWithoutLeadingAndTrailingSpaces($gedcom, Gedcom::LINE_LENGTH);
                }
                else {
                    $gedcom = $this->wrapLongLines($gedcom, Gedcom::LINE_LENGTH);
                }
            }

            //Convert to the requested line ending
            if ($line_endings === 'CRLF') {
                $gedcom = strtr($gedcom, ["\n" => "\r\n"]);
            }

            $bytes_written = fwrite($stream, $gedcom);

            if ($bytes_written !== strlen($gedcom)) {
                throw new RuntimeException('Unable to write to stream.  Perhaps the disk is full?');
            }
        }

        if (rewind($stream) === false) {
            throw new RuntimeException('Cannot rewind temporary stream');
        }

        return $stream;
    }

    /**
     * Create a header record for a gedcom file, which exports SUBM/SUBN even if no user is logged in
     *
     * @param Tree   $tree
     * @param string $encoding
     * @param bool   $include_sub   Include SUBM or SUBN
     *
     * @return string
     */
    public function createHeader(Tree $tree, string $encoding, bool $include_sub): string
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
                    if (strpos($gedcom, "\n1 " . substr($fact->tag(), -4, 4)) === false) {
                        $gedcom .= "\n" . $fact->gedcom();
                    }
                }
            }
        }

        return $gedcom;
    }

    /**
     * Wrap long lines using concatenation records. Try to avoid line splits, which create leading/trailing spaces
     *
     * @param string $gedcom
     * @param int    $max_line_length
     *
     * @return string
     */
    public function wrapLongLinesWithoutLeadingAndTrailingSpaces(string $gedcom, int $max_line_length): string
    {
        $lines = [];

        foreach (explode("\n", $gedcom) as $line) {
            // Split long lines
            // The total length of a GEDCOM line, including level number, cross-reference number,
            // tag, value, delimiters, and terminator, must not exceed 255 (wide) characters.
            if (mb_strlen($line) > $max_line_length) {
                [$level, $tag] = explode(' ', $line, 3);
                if ($tag !== 'CONT') {
                    $level++;
                }
                do {
                    // Split after $pos chars
                    $pos = $max_line_length + 1;

                    // Split between non-space characters in order to avoid leading/trailing spaces
                    while (mb_substr($line, $pos - 1, 1) === ' ' OR mb_substr($line, $pos - 2, 1) === ' ') {
                        --$pos;
                    }
                    --$pos;
                    if ($pos < strpos($line, ' ', 3) + 1 ) {
                        // No double non-space characters in the data! Can’t split it at a better position than max length
                        $pos = $max_line_length;
                    }
                    $lines[] = mb_substr($line, 0, $pos);
                    $line    = $level . ' CONC ' . mb_substr($line, $pos);
                } while (mb_strlen($line) > $max_line_length);
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
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
     * Add to schemas
     * 
     * @param array $schema_uris_for_tags  A list of schemas, which are used for the export
     * @param array $schemas               An array with schemas to add
     * 
     * @return void
     */
    public function addToSchemas(array &$schema_uris_for_tags, array $schemas) : void
    {
        foreach ($schemas as $schema) {
        
            foreach($schema as $uri => $custom_tags) {

                foreach($custom_tags as $tag) {
                    $schema_uris_for_tags[$tag] = $uri;
                }
            }
        }

        return;
    }

    /**
     * Apply GEDCOM filters to a set of GEDCOM structures
     *
     * @param array<string>                 $gedcom_structures   An array with GEDCOM structures
     * @param array<GedcomFilterInterface>  $gedcom_filters      An array with GEDCOM filters
     * @param array<string>                 $matched_pattern_for_tag_combination   An array with matched tag combinations
     * @param array<string>                 $params              Parameters from remote URL requests as well as further parameters, e.g. 'tree' and 'base_url'
     * 
     * @return array<string>                                     An array with Gedcom structures after filter application
     */
    public function applyGedcomFilters(array $gedcom_structures, array $gedcom_filters, array &$matched_pattern_for_tag_combination, array $params = []): array
    {
        foreach($gedcom_filters as $gedcom_filter) {

            if ($gedcom_filter === null) break;

            //Initialize GEDCOM filter
            $gedcom_filter_rules = $gedcom_filter->getGedcomFilterRules($params);
            $gedcom_filter_patterns = array_keys($gedcom_filter_rules);
            $gedcom_filter_rule_has_regexp = $gedcom_filter_patterns;
            $records_references_analysis = $gedcom_filter->usesReferencesAnalysis();
            $records_references = [];
            $matched_pattern_for_tag_combination = [];

            //Globally remember, whether to apply SCHMA tag analysis to the final export
            $this->use_schema_tag_analysis = $this->use_schema_tag_analysis || $gedcom_filter->usesSchemaTagAnalysis();

            //Globally remember, whether to wrap lines without leading/trailing spaces
            $this->wrap_lines_without_leading_and_trailing_spaces = $this->wrap_lines_without_leading_and_trailing_spaces || $gedcom_filter->wrapLinesWithoutLeadingAndTrailingSpaces();

            //Create lookup table if regexp exists for a pattern
            foreach($gedcom_filter_patterns as $pattern) {
                
                //TodDo: Does filter always contain an array??
                $gedcom_filter_rule_has_regexp[$pattern] = $gedcom_filter_rules[$pattern] !== [];
            }

            //If requested, perform empty and not referenced records analysis
            if ($records_references_analysis) {

                //Initialize a list of records as <Record> objects, which contain the references between the records
                //array <string xref => Record record>
                $records_references = [];

                foreach($gedcom_structures as $gedcom) {

                    $this->analyzeRecordsAndReferences($gedcom, $records_references);
                }

                //Identify empty and unlinked records
                if ($records_references_analysis) {
                    $this->identifyEmptyAndUnlinkedRecords($records_references);
                }
            }            

            //Apply filter
            $filtered_gedcom_records = [];

            foreach($gedcom_structures as $gedcom_structure) {

                //Parse if Gedcom contains several level 0 structures, e.g. created by an earlier filter
                $gedcom_records = $this->parseTopLevelStructures($gedcom_structure);

                foreach($gedcom_records as $gedcom) {

                    $gedcom = $this->executeFilter($gedcom, 0, '', '', $gedcom_filter, $params, $gedcom_filter_patterns, $gedcom_filter_rules, $gedcom_filter_rule_has_regexp, $matched_pattern_for_tag_combination, $records_references);

                    if ($gedcom !== '') {
                        $filtered_gedcom_records[] = $gedcom;
                    }    
                }
            }
            $gedcom_structures = $filtered_gedcom_records;
        }
             
        return $gedcom_structures;
    }

    /**
     * Convert Gedcom record according to an GEDCOM filter
     *
     * @param string $gedcom
     * @param int    $level                                Level of Gedcom structure
     * @param string $higher_level_matched_tag_pattern     Pattern, which was matched on higher level of GEDCOM structure (recursion)
     * @param string $tag_combination                      e.g. INDI:BIRT:DATE
     * @param GedcomFilterInterface $gedcom_filter         The GEDCOM filter used
     * @param array<string>         $params                Parameters from remote URL requests as well as further parameters, e.g. 'tree' and 'base_url'
     * @param array  $gedcom_filter_patterns               The patterns of the GEDCOM filter
     * @param array  $gedcom_filter_rules                  The filter rules of the GEDCOM filter
     * @param array  $gedcom_filter_rule_has_regexp        A lookup table whether a filter rules uses a regular expression
     * @param array  $matched_pattern_for_tag_combination  A hash table, which contains patterns, which have already been matched
     * @param array  $records_references                   A list of records as <Record> objects, which contain the references between the records
     *                                                     array <string xref => Record record>
     *
     * @return string                                      Converted Gedcom
     */
    public function executeFilter(
        string $gedcom,
        int    $level,
        string $higher_level_matched_tag_pattern,
        string $tag_combination,
        GedcomFilterInterface $gedcom_filter,
        array $params = [],
        array  &$gedcom_filter_patterns,
        array  &$gedcom_filter_rules,
        array  &$gedcom_filter_rule_has_regexp,
        array  &$matched_pattern_for_tag_combination,
        array  &$records_references
        ): string
    {   
        $converted_gedcom = '';

        try {
            if ($level === 0) {
                preg_match('/0( @' . Gedcom::REGEX_XREF . '@)* (' . Gedcom::REGEX_TAG . ')\b ?(.*)/', $gedcom, $match);
                $tag = $match[2];
            }
            else {
                preg_match('/' . $level . ' (' . Gedcom::REGEX_TAG . ')\b ?(.*)/', $gedcom, $match);    
                $tag = $match[1];
            }
        }
        catch (Throwable $th) {
            $message = I18N::translate('The following GEDCOM structure could not be matched') . ': ' . $gedcom;
            throw new DownloadGedcomWithUrlException($message);
        }

        if ($tag_combination === '') {
            $tag_combination = $tag;
        } 
        else {
            $tag_combination .= ':' . $tag;
        }

        //Get matched pattern from hash table (if already matched before), otherwise evaluate matched pattern 
        $matched_tag_pattern = $matched_pattern_for_tag_combination[$tag_combination] ?? self::getMatchedPattern($tag_combination, $gedcom_filter_patterns);

        //Add found tag pattern to the hash table; even if empty, i.e ''
        $matched_pattern_for_tag_combination[$tag_combination] = $matched_tag_pattern;

        //If tag pattern was found, add the related Gedcom
        if ($matched_tag_pattern !== '') {
           
            $converted_gedcom = $match[0] ."\n";
        }

        //Get sub-structure of Gedcom and recursively apply GEDCOM filter to next level
        $gedcom_substructures = self::parseGedcomSubstructures($gedcom, $level + 1);

        foreach ($gedcom_substructures as $gedcom_substructure) {

            $converted_gedcom .= $this->executeFilter($gedcom_substructure, $level + 1, $matched_tag_pattern, $tag_combination, $gedcom_filter, $params, $gedcom_filter_patterns, $gedcom_filter_rules, $gedcom_filter_rule_has_regexp, $matched_pattern_for_tag_combination, $records_references);
        }

        //If regular expressions are provided for the pattern, run replacements
        //Do not replace again if pattern has already been matched on higher level of the Gedcom structure
        if (   $matched_tag_pattern !== ''   
            && $gedcom_filter_rule_has_regexp[$matched_tag_pattern] 
            && $matched_tag_pattern !== $higher_level_matched_tag_pattern) {

            $replace_pairs = $gedcom_filter_rules[$matched_tag_pattern];                
            $converted_gedcom = $this->replaceInGedcom($converted_gedcom, $matched_tag_pattern, $replace_pairs, $gedcom_filter, $params, $records_references);
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
    public static function getMatchedPattern(string $tag, array &$patterns): string
    {
        $i = 0;
        $size = sizeof($patterns);
        $is_white_list_pattern = false;
        $match = false;

        while ($i < $size && !$match) {
 
            $pattern = $patterns[$i];

            //If is black list pattern
            if (strpos($pattern, '!') === 0) {
                
                //Remove '!' from pattern 
                $pattern = substr($pattern, 1);
                $is_white_list_pattern = false;
            }
            else {
                $is_white_list_pattern = true;
            }
    
            $match = self::matchTagWithSinglePattern($tag, $pattern);
            $i++;
        }

        //If white list match return matched pattern
        if ($is_white_list_pattern && $match) return $patterns[$i-1] ?? '';

        //If black list match or nothing found, return empty match
        return '';
    }

    /**
     * Match a given tag (e.g. FAM:MARR:DATE) with a tag pattern (e.g. FAM:*:DATE)
     *
     * @param string     $tag                   e.g. FAM:MARR:DATE
     * @param string     $pattern               e.g. FAM:*:DATE
     *
     * @return bool      Whether the tag could be matched or not      
     */
    public static function matchTagWithSinglePattern(string $tag, string $pattern): bool
    {          
        $tag_token_size =     preg_match_all('/(' . Gedcom::REGEX_TAG . '|[\*)])((?!\:)(' . Gedcom::REGEX_TAG . '|[\*)]))*/', $tag, $tag_tokens, PREG_PATTERN_ORDER);
        $pattern_token_size = preg_match_all('/(' . Gedcom::REGEX_TAG . '|[\*)])((?!\:)(' . Gedcom::REGEX_TAG . '|[\*)]))*/', $pattern, $pattern_tokens, PREG_PATTERN_ORDER);

        //Return false if nothing was found
        if ($tag_token_size === 0 OR $pattern_token_size === 0) return false;

    	if ($tag_token_size < $pattern_token_size) {

            //If tag contains less tokens than pattern and tag does not end with *, return false
            if ($tag_tokens[0][$tag_token_size - 1] !== '*') {
                return false;
            }
            //If tag ends with *, only pattern tokens until the length of the pattern need to be checked
            else {
                $pattern_token_size = $tag_token_size -1;
            }   
        }
    	elseif ($tag_token_size > $pattern_token_size) {

            //If tag contains more tokens than pattern and pattern does not end with *, return false
            if ($pattern_tokens[0][$pattern_token_size - 1] !== '*') {
                return false;
            }
            //If pattern ends with *, only tag tokens until the length of the pattern need to be checked
            else {
                $tag_token_size = $pattern_token_size -1;
            }        
        }

        //Compare tag and pattern
        $i = 0;
        $match = true;

        while ($i < $tag_token_size && $match) {

            if ($pattern_tokens[0][$i] !== '*' && $pattern_tokens[0][$i] !== $tag_tokens[0][$i]) $match = false;    
            $i++;
        }

        return $match;
    }

    /**
     * Convert Gedcom based on the matched pattern of a filter rule, 
     * which points to an array of RegExp replace pairs or cutom conversions
     *
     * @param string                $gedcom               Gedcom to convert
     * @param string                $matched_pattern      The matched pattern (i.e. INDI:NAME) of the filter rule, whose replacements shall be applied
     * @param array                 $replace_pairs        An array with replace pairs, i.e. ["search pattern" => "replace pattern"] 
     * @param GedcomFilterInterface $gedcom_filter        The GEDCOM filter used
     * @param array<string>         $params               Parameters from remote URL requests as well as further parameters, e.g. 'tree' and 'base_url'
     * @param array                 $records_references   A list of records as <Record> objects, which contain the references between the records
     *                                                    array <string xref => Record record>
     *
     * @return string                                     Converted Gedcom
     */
    private function replaceInGedcom(
        string                $gedcom,
        string                $matched_pattern,       
        array                 $replace_pairs,
        GedcomFilterInterface $gedcom_filter,
        array                 $params,
        array                 &$records_references
        ): string {

        //For each replacement, which is provided
        foreach ($replace_pairs as $search => $replace) {

            //If according string is found, apply custom conversion
            if ($search === AbstractGedcomFilter::PHP_FUNCTION_STRING) {

                $gedcom = $gedcom_filter->customConvert($matched_pattern, $gedcom, $records_references, $params);
            }

            //Else apply RegExp replacement
            else { 
                try {
                     $gedcom = preg_replace($search, $replace, $gedcom) ?? '';
                }
                catch (Throwable $th) {
                    $message = I18N::translate('Error during a regular expression replacement.') . ' Gedcom: ' . $gedcom . ' Search: ' . $search  . ' Replace: ' . $replace . "\nError message:\n" . $th->getMessage();
                    throw new DownloadGedcomWithUrlException($message);
                }
            }
        }

        return $gedcom;
    }

    /**
     * Split a Gedcom string into Gedcom sub structures
     *
     * @param string $gedcom
     * @param int    $level    The level, at which the Gedcom structure shall be splitted
     * 
     * @return array<string>
     */
    public static function parseGedcomSubstructures(string $gedcom, int $level): array
    {
        // Split the Gedcom strucuture into sub structures 
        // See: Fisharebest\Webtrees\GedcomRecord, function parseFacts()
        $gedcom_substructures = preg_split('/\n(?=' . $level . ')/', $gedcom);

        //Delete first structure, which is from one Gedcom level up 
        unset($gedcom_substructures[0]);

        return $gedcom_substructures;
    }

    /**
     * Split a Gedcom string into level 0 Gedcom structures, i.e. records or HEAD/TRLR
     * Due to performance reasons, a separate method is used for this specific case
     *
     * @param string $gedcom
     * 
     * @return array<string>
     */
    public static function parseTopLevelStructures(string $gedcom): array
    {
        // Split the Gedcom strucuture into level 0 structures 
        // See: Fisharebest\Webtrees\GedcomRecord, function parseFacts()
        if ($gedcom !== '') {

            $gedcom_records = preg_split("/\n(?=0)/", $gedcom, -1, PREG_SPLIT_DELIM_CAPTURE);
            return $gedcom_records;
        } 
        else {
            return [];
        }
    }
    
    /**
     * Perform an analysis of records their references and create a record list and links between the records
     *
     * @param string $gedcom
     * @param array  $records_references  A list of records as <Record> objects, which contain the references between the records
     *                                    array <string xref => Record record>
     * 
     * @return void
     */
    private function analyzeRecordsAndReferences(string $gedcom, array &$records_references) : void {

        //Match xref
        preg_match('/0 @(' . Gedcom::REGEX_XREF . ')@ (' . Gedcom::REGEX_TAG . ')(.*)\n(.*)/', $gedcom, $match);
        $xref = $match[1] ?? '';
        $record_type = $match[2] ?? '';
        $text = $match[3] ?? '';
        $record_content = $match[4] ?? '';

        //Specific treatment of HEAD and TRLR
        if ($xref === '') {
            preg_match('/0 (HEAD|TRLR)/', $gedcom, $match);
            $xref = $match[1] ?? '';
            $record_type = $xref;
            $text = $xref;
        }

        //If not exists, create record and add to records list
        if (!isset($records_references[$xref])) {

            $record = new Record($xref, $record_type);
            $records_references[$xref] = $record;
        }
        else {
            $record = $records_references[$xref];

            //Add type if is not set already, i.e. a reference has been found earlier and type could not be identified
            if ($record->type() === '') {

                $record->setTpye($record_type);
            }
        }

        //If no sub-structure exists, set record to empty
        if ($text === '' && $record_content === '') {

            $record->setEmpty();
        }

        //Analyse whether the record is a minimal individual, i.e. INDI record with SEX, FAMC, FAMS or less
        if ($record_type === 'INDI') {

            $sub_structures = $this->parseGedcomSubstructures($gedcom, 1);
            $minimal = true;

            foreach ($sub_structures as $sub_structure) {
                if (preg_match('/1 (' . Gedcom::REGEX_TAG . ').*/', $sub_structure, $match)) {
                    $tag = $match[1];

                    if (!in_array($tag, ['SEX', 'FAMC', 'FAMS'])) {
                        $minimal = false;
                        break;
                    }    
                }
            }

            if ($minimal) {
                $record->setMinimalIndividual();
            }
        }

        //Match <XREF:*> references
        preg_match_all('/[\d] '. Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@/', $gedcom, $matches);

        foreach ($matches[1] as $match) {

            //If not exists, create record for reference and add to records list
            if (!isset($records_references[$match])) {

                $referenced = new Record($match, '');  //Unfortunatelly, we do not know the type. Therefore, set type to ''
                $records_references[$match] = $referenced;
            }
            else {
                $referenced = $records_references[$match];
            }

            //Link record to referenced record
            $record->addReferencedRecord($referenced);

            //Link back referenced to record
            $referenced->addReferencingRecord($record);
        }

        return;
    }

    /**
     * Identify empty and unlinked records in the record list and update references of related records
     * 
     * @param array  $records_references  A list of records as <Record> objects, which contain the references between the records
     *                                    array <string xref => Record record>
     * 
     * @return void
     */
    private function identifyEmptyAndUnlinkedRecords(array &$records_references) : void {
        
        $modified_references = true;
        $iteration = 0;

        while ($modified_references) {

            $modified_references = false;
            $iteration++;

            //Iterate over all records in the record list
            foreach ($records_references as $xref => $record) {

                $propagate_result = $this->propagateReferences($record);
                $modified_references = $modified_references || $propagate_result;
            }

            if ($iteration > 100) throw new DownloadGedcomWithUrlException(I18N::translate('Fatal error: Too many iterations while removing empty and unlinked records.'));
        }

        return;
    }

    /**
     * Propagate references from empty and unlined records to linked records
     * 
     * @param Record $record
     * 
     * @return bool             True if references of record (or sub structure) were modified, i.e. empty or unlinked record identified
     */
    private function propagateReferences(Record &$record) : bool {

        $modified_references = false;

        //If record is empty or has no references and record is not HEAD|TRLR|INDI
        if ((   $record->isEmpty() OR !$record->isReferenced())
                && !in_array($record->type(), ['HEAD', 'TRLR', 'INDI'])) {

            //Iterate over all records referencing the record
            foreach($record->getReferencingRecords() as $referencing_record) {

                //Remove links between record and referencing record
                $record->removeReferencingRecord($referencing_record);
                $referencing_record->removeReferencedRecord($record);
                $modified_references = true;
            }

            //Iterate over all records referenced by the record
            foreach($record->getReferencedRecords() as $record_referenced) {

                //Remove links between record and referenced record
                $record->removeReferencedRecord($record_referenced);
                $record_referenced->removeReferencingRecord($record);
                $modified_references = true;

                //Run recursion on referenced record
                $this->propagateReferences($record_referenced);
            }
        }

        return $modified_references;
    }

    /**
     * Assess whether a Gedcom structure contains a Gedcom 7 header
     * 
     * @param string $gedcom    Gedcom structure
     * 
     * @return bool             True if is a Gedcom 7 header, otherwise false
     */
    private function isGedcom7Header(string $gedcom) : bool {

        preg_match('/0 (HEAD|TRLR)/', $gedcom, $match);
        $record_type= $match[1] ?? '';

        if ($record_type !== 'HEAD') return false;
    
        return preg_match("/1 GEDC\n2 VERS 7/", $gedcom, $match) === 1;
    }

    /**
     * Add SCHMA structure to a Gedcom 7 header
     * 
     * @param array<string>  $gedcom_structures         An array with the Gedcom structures, i.e.HEAD, all records, TRLRL
     * @param array<string>  $matched_tag_combinations  An array with tag combinations in the array keys
     * 
     * @return array<string>                            Gedcom structures with added SCHMA in the header 
     */
    private function addSchema(array &$gedcom_structures, &$matched_tag_combinations) : void {

        //Assume Gedcom 7 export, if first item in record list is a Gedcom 7 header
        if ($this->isGedcom7Header($gedcom_structures[0] ?? false)) {

            if (empty($matched_tag_combinations)) {

                //Filter with AllRecordsGedcomFilter in order to get a complete list of tag combinations (returned in $matched_tag_combinations)
                $this->applyGedcomFilters($gedcom_structures, [new AllRecordsGedcomFilter()], $matched_tag_combinations);
            }

            //Create a list of schemas, which are used for the export
            $schema_uris_for_tags = [];
            $this->addToSchemas($schema_uris_for_tags, self::SCHEMAS);
            $this->addToSchemas($schema_uris_for_tags, self::GEDCOM_L_SCHEMAS);

            //Find custom tags
            $custom_tags_with_schema = [];

            foreach($matched_tag_combinations as $tag_combination => $matched_filter_rule) {

                preg_match_all('/([_A-Z0-9\*]+)((?!\:)[_A-Z0-9\*]+)*/', $tag_combination, $tags, PREG_PATTERN_ORDER);

                foreach ($tags[0] as $tag) {

                    if (strpos($tag, '_') === 0 && !in_array($tag, $custom_tags_with_schema)) {

                        if (isset($schema_uris_for_tags[$tag])) {

                            $custom_tags_with_schema[] = $tag;
                        }
                    }
                }
            }

            //Get Gedcom of HEAD
            $head_gedcom = $gedcom_structures[0]; 

            //Append SCHMA structure to HEAD
            if (sizeof($custom_tags_with_schema) > 0) {

                $head_gedcom .= "1 SCHMA\n";

                foreach($custom_tags_with_schema as $tag) {

                    $head_gedcom .= "2 TAG " . $tag . " " . $schema_uris_for_tags[$tag] . "\n";
                }
            }
            
            //Set new Gedcom for HEAD
            $gedcom_structures[0] = $head_gedcom;
        }    

        return;
    }
}
