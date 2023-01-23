<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2022 webtrees development team
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

namespace DownloadGedcomWithURLNamespace;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Encodings\UTF16BE;
use Fisharebest\Webtrees\Encodings\UTF16LE;
use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\Encodings\Windows1252;
use Fisharebest\Webtrees\Factories\AbstractGedcomRecordFactory;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomFilters\GedcomEncodingFilter;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Header;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Webtrees;
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

use function addcslashes;
use function date;
use function explode;
use function fclose;
use function fopen;
use function fwrite;
use function is_string;
use function pathinfo;
use function preg_match_all;
use function preg_replace;
use function rewind;
use function stream_filter_append;
use function stream_get_meta_data;
use function strlen;
use function strpos;
use function strtolower;
use function strtoupper;
use function tmpfile;

use const PATHINFO_EXTENSION;
use const PREG_SET_ORDER;
use const STREAM_FILTER_WRITE;

/**
 * Export data in GEDCOM format
 */
class GedcomSevenExportService
{
    private const ACCESS_LEVELS = [
        'gedadmin' => Auth::PRIV_NONE,
        'user'     => Auth::PRIV_USER,
        'visitor'  => Auth::PRIV_PRIVATE,
        'none'     => Auth::PRIV_HIDE,
    ];

    private ResponseFactoryInterface $response_factory;

    private StreamFactoryInterface $stream_factory;

	private array $language_to_code_table;

    /**
     * @param ResponseFactoryInterface $response_factory
     * @param StreamFactoryInterface   $stream_factory
     */
	public function __construct(ResponseFactoryInterface $response_factory, StreamFactoryInterface $stream_factory)
	{
		$this->response_factory = $response_factory;
		$this->stream_factory   = $stream_factory;

		$iana_language_registry_file_name = __DIR__ . '/vendor/iana/iana_languages.txt';

		$iana_language_registry = file_get_contents($iana_language_registry_file_name);

		//Create language table
		preg_match_all("/Type: language\nSubtag: ([^\n]+)\nDescription: ([^\n]+)\n/", $iana_language_registry, $matches, PREG_SET_ORDER);

		foreach ($matches as $match) {
			$this->language_to_code_table[strtoupper($match[2])]= $match[1];
		}
	}

    /**
     * @param Tree            $tree         - Export data from this tree
     * @param bool            $sort_by_xref - Write GEDCOM records in XREF order
     * @param string          $encoding     - Convert from UTF-8 to other encoding
     * @param string          $privacy      - Filter records by role
     * @param string          $line_endings
     * @param string          $filename     - Name of download file, without an extension
     * @param string          $format       - One of: gedcom, zip, zipmedia, gedzip
     * @param Collection|null $records
     *
     * @return ResponseInterface
     */
    public function downloadGedcomSevenResponse(
        Tree $tree,
        bool $sort_by_xref,
        string $encoding,
        string $privacy,
        string $line_endings,
        string $filename,
        string $format,
        Collection $records = null
    ): ResponseInterface {
        $access_level = self::ACCESS_LEVELS[$privacy];

        if ($format === 'gedcom') {
            $resource = $this->export($tree, $sort_by_xref, $encoding, $access_level, $line_endings, $records);
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

        $resource = $this->export($tree, $sort_by_xref, $encoding, $access_level, $line_endings, $records, $zip_filesystem, $media_path);

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
     * @param Tree                        $tree           - Export data from this tree
     * @param bool                        $sort_by_xref   - Write GEDCOM records in XREF order
     * @param string                      $encoding       - Convert from UTF-8 to other encoding
     * @param int                         $access_level   - Apply privacy filtering
     * @param string                      $line_endings   - CRLF or LF
     * @param Collection<int,string>|null $records        - Just export these records
     * @param FilesystemOperator|null     $zip_filesystem - Write media files to this filesystem
     * @param string|null                 $media_path     - Location within the zip filesystem
     *
     * @return resource
     */
    public function export(
        Tree $tree,
        bool $sort_by_xref = false,
        string $encoding = UTF8::NAME,
        int $access_level = Auth::PRIV_HIDE,
        string $line_endings = 'CRLF',
        Collection $records = null,
        FilesystemOperator $zip_filesystem = null,
        string $media_path = null
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

				//Do NOT wrap long lines
                //$gedcom = $this->wrapLongLines($gedcom, Gedcom::LINE_LENGTH) . "\n";
				$gedcom .= "\n";

				//Convert to Gedcom 7
				$gedcom = $this->convertToGedcom7($gedcom);

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
     * Convert to Gedcom 7
     *
     * @param string $gedcom
     *
     * @return string
     */
    public function convertToGedcom7(string $gedcom): string
    {
		$replace_pairs = [
			"1 CHAR UTF-8\n" => "",
			"2 RELA " => "2 ROLE ",
			"3 RELA " => "3 ROLE ",
			"ROLE (Godparent)\n" => "ROLE GODP\n",
			"ROLE godparent\n" => "ROLE GODP\n",
			"TYPE married\n" => "TYPE MARRIED\n",
			"2 _ASSO" => "2 ASSO",
			"2 PEDI birth\n" => "2 PEDI BIRTH\n",
			"2 PEDI adopted\n" => "2 PEDI ADOPTED\n",
			"2 TYPE RELI\n" => "2 TYPE RELIGIOUS\n",
			"2 LANG SERB\n" => "2 LANG Serbian\n",
			"2 LANG Serbo_Croa\n" => "2 LANG Serbo-Croatian\n",
			"2 LANG BELORUSIAN\n" => "2 LANG Belarusian\n",
		];

		foreach ($replace_pairs as $search => $replace) {
			$gedcom = str_replace($search, $replace, $gedcom);
		}

		$preg_replace_pairs = [
			"/0([\d]) (JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC) (.[\d]{1,4})/" => "$1 $2 $3",
			"/2 AGE 0([\d]{1,2})y/" => "2 AGE $1y",
			"/2 AGE ([\d]{1,3})y 0(.)m/" => "2 AGE $1y $2m",
			"/2 AGE ([\d]{1,3})y ([\d]{1,2})m 0([\d]{1,2})d/" => "2 AGE $1y $2m $3d",
			"/2 AGE ([\d]{1,2})m 00([\d])d/" => "2 AGE $1m $2d",
			"/2 AGE ([\d]{1,2})m 0([\d]{1,2})d/" => "2 AGE $1m $2d",
			"/1 FILE .+\.ged\n/" => "",
			"/2 RESN .+\n/" => "",
			"/2 FORM (jpg|JPG)\n3 TYPE .[^\n]+/" => "2 FORM image/jpeg",
			"/2 FORM (gif|GIF)\n3 TYPE .[^\n]+/" => "2 FORM image/gif",
			"/2 FORM (pdf|PDF)\n3 TYPE .[^\n]+/" => "2 FORM application/pdf",
		];

		foreach ($preg_replace_pairs as $pattern => $replace) {
			$gedcom = preg_replace($pattern, $replace, $gedcom);
		}

		//Languages
		preg_match_all("/([12]) LANG (.[^\n]+)\n/", $gedcom, $matches, PREG_SET_ORDER);

		foreach ($matches as $match) {

			if (isset($this->language_to_code_table[strtoupper($match[2])])) {

				$search =  $match[1] . " LANG " . $match[2] . "\n";
				$replace = $match[1] . " LANG " . $this->language_to_code_table[strtoupper($match[2])] . "\n";
				$gedcom = str_replace($search, $replace, $gedcom);
			}
		}

		//Roles
		preg_match_all("/([\d]) ROLE (.[^\n]+)/", $gedcom, $matches, PREG_SET_ORDER);

		foreach ($matches as $match) {

			//If not TAG 
			if (!((strlen($match[2]) === 4) && (strtoupper($match[2]) === $match[2]))) {

				$level = (int) $match[1];
				$next_level = (string) $level + 1;
				$search =  $level . " ROLE " . $match[2];
				$replace = $level . " ROLE OTHER\n" . $next_level . " PHRASE " . $match[2];
				$gedcom = str_replace($search, $replace, $gedcom);
			}
		}		

		//Types
		$allowed_types = [
			"ADOPTED",
			"BIRTH",
			"FOSTER",
			"SEALING",
			"AKA",
			"BIRTH",
			"IMMIGRANT",
			"MAIDEN",
			"MARRIED",
			"PROFESSIONAL",
			"CIVIL",
			"RELIGIOUS",
		];

		preg_match_all("/1 NAME (.[^\n]+)\n2 TYPE (.[^\n]+)/", $gedcom, $matches, PREG_SET_ORDER);

		foreach ($matches as $match) {

			if (sizeof($match) < 3) {
				$found_type =  $match[1];
			} 
			else {
				$found_type =  $match[2];				
			}

			//If allowed type
			if (in_array(strtoupper($found_type), $allowed_types)) {
				$search =  "2 TYPE " . $found_type;
				$replace = "2 TYPE " . strtoupper($found_type);
				$gedcom = str_replace($search, $replace, $gedcom);
			}
			//Use phrase instead
			else {
				$search =  "2 TYPE " . $found_type;
				$replace = "2 TYPE OTHER\n3 PHRASE " . $found_type;
				$gedcom = str_replace($search, $replace, $gedcom);
			}
		}		

		return $gedcom;
	}
	
    /**
     * Create a header record for a gedcom file.
     *
     * @param Tree   $tree
     * @param string $encoding
     * @param bool   $include_sub
     *
     * @return string
     */
    public function createHeader(Tree $tree, string $encoding, bool $include_sub): string
    {
        // Force a ".ged" suffix
        $filename = $tree->name();

        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'ged') {
            $filename .= '.ged';
        }

        $gedcom_encodings = [
            UTF16BE::NAME     => 'UNICODE',
            UTF16LE::NAME     => 'UNICODE',
            Windows1252::NAME => 'ANSI',
        ];

        $encoding = $gedcom_encodings[$encoding] ?? $encoding;

        // Build a new header record
        $gedcom = '0 HEAD';
        $gedcom .= "\n1 SOUR " . Webtrees::NAME;
        $gedcom .= "\n2 NAME " . Webtrees::NAME;
        $gedcom .= "\n2 VERS " . Webtrees::VERSION;
        $gedcom .= "\n1 DEST DISKETTE";
        $gedcom .= "\n1 DATE " . strtoupper(date('d M Y'));
        $gedcom .= "\n2 TIME " . date('H:i:s');
        $gedcom .= "\n1 GEDC\n2 VERS 7.0.11";
        $gedcom .= "\n1 CHAR " . $encoding;
        $gedcom .= "\n1 FILE " . $filename;

		//Add schema with extension tags
		$gedcom .= "\n1 SCHMA ";
		$gedcom .= "\n2 TAG _GOVTYPE https://genealogy.net/GEDCOM/ ";
		$gedcom .= "\n2 TAG _STAT https://genealogy.net/GEDCOM/ ";
		$gedcom .= "\n2 TAG _WITN https://genealogy.net/GEDCOM/ ";
		$gedcom .= "\n2 TAG _RUFNAME https://genealogy.net/GEDCOM/ ";
		$gedcom .= "\n2 TAG _GODP https://genealogy.net/GEDCOM/ ";
		$gedcom .= "\n2 TAG _ASSO https://genealogy.net/GEDCOM/ ";
		$gedcom .= "\n2 TAG _LOC https://genealogy.net/GEDCOM/ ";
		$gedcom .= "\n2 TAG _GOV https://genealogy.net/GEDCOM/ ";

        // Preserve some values from the original header
        $header = Registry::headerFactory()->make('HEAD', $tree) ?? Registry::headerFactory()->new('HEAD', '0 HEAD', null, $tree);

        foreach ($header->facts(['COPR', 'LANG', 'PLAC', 'NOTE']) as $fact) {
            $gedcom .= "\n" . $fact->gedcom();
        }

        if ($include_sub) {
            foreach ($header->facts(['SUBM', 'SUBN']) as $fact) {
                $gedcom .= "\n" . $fact->gedcom();
            }
        }

        return $gedcom;
    }

    /**
     * Wrap long lines using concatenation records.
     *
     * @param string $gedcom
     * @param int    $max_line_length
     *
     * @return string
     */
    public function wrapLongLines(string $gedcom, int $max_line_length): string
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
                    $pos = $max_line_length;
                    // Split on a non-space (standard gedcom behavior)
                    while (mb_substr($line, $pos - 1, 1) === ' ') {
                        --$pos;
                    }
                    if ($pos === strpos($line, ' ', 3)) {
                        // No non-spaces in the data! Can’t split it :-(
                        break;
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
}
