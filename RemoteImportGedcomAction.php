<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2024 webtrees development team
 *                    <http://webtrees.net>

 * DownloadGedcomWithURL (webtrees custom module):
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
 */

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\GedcomFilters\GedcomEncodingFilter;
use Fisharebest\Webtrees\Http\RequestHandlers\ManageTrees;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Illuminate\Support\Collection;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToReadFile;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Throwable;

/**
 * Remotely import a GEDCOM file into a tree.
 */
class RemoteImportGedcomAction implements RequestHandlerInterface
{
    private StreamFactoryInterface $stream_factory;

    private TreeService $tree_service;

    private ModuleService $module_service;

    private ResponseFactoryInterface $response_factory;

    private GedcomExportFilterService $gedcom_export_service;

    /**
     * @param StreamFactoryInterface $stream_factory
     * @param TreeService            $tree_service
     */
    public function __construct()
    {
        $this->tree_service   = new TreeService(new GedcomImportService);
        $this->stream_factory = new Psr17Factory();
        $this->module_service = new ModuleService();
        $this->response_factory = app(ResponseFactoryInterface::class);
        $this->gedcom_export_service = new GedcomExportFilterService($this->response_factory, $this->stream_factory);
    }

    /**
     * Import data from a gedcom file to an array of Gedcom record strings.
     *
     * @param StreamInterface      $stream   The GEDCOM file.
     * @param string               $encoding Override the encoding specified in the header.
     *
     * @return array<string>                 A set of Gedcom record strings
     */
    public function importGedcomFile(Tree $tree, StreamInterface $stream, string $filename, string $encoding): array
    {
        $gedcom_records = [];

        // Read the file in blocks of roughly 64K. Ensure that each block
        // contains complete gedcom records. This will ensure we don’t split
        // multi-byte characters, as well as simplifying the code to import
        // each block.

        $file_data = '';
        $stream = $stream->detach();

        // Convert to UTF-8.
        stream_filter_append($stream, GedcomEncodingFilter::class, STREAM_FILTER_READ, ['src_encoding' => $encoding]);

        while (!feof($stream)) {
            $file_data .= fread($stream, 65536);
            $eol_pos = max((int) strrpos($file_data, "\r0"), (int) strrpos($file_data, "\n0"));

            if ($eol_pos > 0) {
                $chunk_data = substr($file_data, 0, $eol_pos + 1);
                $chunk_data = str_replace("\r\n", "\n", $chunk_data);

                $remaining_string = $this->addToGedcomRecords($gedcom_records, $chunk_data);

                $file_data = $remaining_string . substr($file_data, $eol_pos + 1);
            }
        }

        $chunk_data = $file_data;
        $chunk_data = str_replace("\r\n", "\n", $chunk_data);
        $remaining_string = $this->addToGedcomRecords($gedcom_records, $chunk_data);
        $gedcom_records[] = $remaining_string;

        fclose($stream);

        return $gedcom_records;
    }

    /**
     * Add data from a chunk of Gedcom data to a set of Gedcom records
     *
     * @param array<string>  $gedcom_records   A set of Gedcom records
     * @param string         $chunk_data       A chunk of Gedcom data
     *
     * @return string                          The remaining end of the chunk 
     */
    public function addToGedcomRecords(array &$gedcom_records, string &$chunk_data): string
    {
        // Split the Gedcom strucuture into sub structures 
        // See: Fisharebest\Webtrees\GedcomRecord, function parseFacts()
        $parsed_gedcom_structures = preg_split('/\n(?=0)/', $chunk_data);
        $size = sizeof($parsed_gedcom_structures);
        $last_string = $parsed_gedcom_structures[$size-1];
        unset($parsed_gedcom_structures[$size-1]);

        //Add to the set of Gedcom records
        $gedcom_records = array_merge($gedcom_records, $parsed_gedcom_structures);

        return $last_string;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     * @throws FilesystemException
     * @throws UnableToReadFile
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $download_gedcom_with_URL = $this->module_service->findByName(DownloadGedcomWithURL::activeModuleName());
        $gedcom_export_filter_service = new GedcomExportFilterService($this->response_factory, $this->stream_factory);
        $gedcom_conversion = true;

        $encoding       = $download_gedcom_with_URL->getPreference(DownloadGedcomWithURL::PREF_DEFAULT_ENCODING, UTF8::NAME);
        $privacy        = $download_gedcom_with_URL->getPreference(DownloadGedcomWithURL::PREF_DEFAULT_PRIVACY_LEVEL, 'none');
        $line_endings   = $download_gedcom_with_URL->getPreference(DownloadGedcomWithURL::PREF_DEFAULT_ENDING, 'CRLF');
        $format         = $download_gedcom_with_URL->getPreference(DownloadGedcomWithURL::PREF_DEFAULT_EXPORT_FORMAT, 'gedcom');

        $key                  = Validator::queryParams($request)->string('key', '');

        //Check preferences if upload is allowed
        $allow_remote_upload         = boolval($download_gedcom_with_URL->getPreference(DownloadGedcomWithURL::PREF_ALLOW_REMOTE_UPLOAD, '0'));

        //An upload from control panel is recognized if a certain key is received
        $called_from_control_panel = $key === $download_gedcom_with_URL->getPreference(DownloadGedcomWithURL::PREF_CONTROL_PANEL_SECRET_KEY, '') . Session::getCsrfToken();
        
        //If upload from control panel
        if ($called_from_control_panel) {
            $tree_name        = Validator::queryParams($request)->string('tree', $download_gedcom_with_URL->getPreference(DownloadGedcomWithURL::PREF_DEFAULT_TREE_NAME, ''));
            $file_name        = Validator::queryParams($request)->string('file',  $download_gedcom_with_URL->getPreference(DownloadGedcomWithURL::PREF_DEFAULT_FiLE_NAME, ''));
        }
        //Otherwise treat as remote upload called with URL
        else {
            try {           
                $tree_name    = Validator::queryParams($request)->string('tree');
                $file_name    = Validator::queryParams($request)->string('file');
            }
            catch (Throwable $ex) {
                $message = I18N::translate('One of the parameters "file, tree" is missing in the called URL.');
                return $download_gedcom_with_URL->showErrorMessage($message);
            }    
        }

        //If not called from control panel (i.e. called remotely via URL), evaluate key
        if (!$called_from_control_panel) {

            //Error if upload is not allowed
            if (!$allow_remote_upload) {
                return $download_gedcom_with_URL->showErrorMessage( I18N::translate('Remote requests to upload GEDCOM files via URL are not allowed.') .  ' '. 
                                                                    I18N::translate('Please check the module settings in the control panel.'));
            }

            //Load secret key from preferences
            $secret_key = $download_gedcom_with_URL->getPreference(DownloadGedcomWithURL::PREF_SECRET_KEY, ''); 
        
            //Error if key is empty
            if ($key === '') {
                return $download_gedcom_with_URL->showErrorMessage(I18N::translate('No key provided. For checking of the access rights, it is mandatory to provide a key as parameter in the URL.'));
            }
            //Error if secret key is empty
            elseif ($secret_key === '') {
                return $download_gedcom_with_URL->showErrorMessage(I18N::translate('No secret key defined. Please define secret key in the module settings: Control Panel / Modules / All Modules / ') . $download_gedcom_with_URL->title());
            }
            //Error if no hashing and key is not valid
            elseif (!boolval($download_gedcom_with_URL->getPreference(DownloadGedcomWithURL::PREF_USE_HASH, '0')) && ($key !== $secret_key)) {
                return $download_gedcom_with_URL->showErrorMessage(I18N::translate('Key not accepted. Access denied.'));
            }
            //Error if hashing and key does not fit to hash
            elseif (boolval($download_gedcom_with_URL->getPreference(DownloadGedcomWithURL::PREF_USE_HASH, '0')) && (!password_verify($key, $secret_key))) {
                return $download_gedcom_with_URL->showErrorMessage(I18N::translate('Key (encrypted) not accepted. Access denied.'));
            }
        }

        //Get tree and error if tree name is not valid
        try {
            $tree = $this->tree_service->all()[$tree_name];
            assert($tree instanceof Tree);
        }
        catch (Throwable $ex) {
            $message = I18N::translate('Could not find the requested tree "%s".', $tree_name);
            return $download_gedcom_with_URL->showErrorMessage($message);
        }        

        //Get folder from module settings, create server file name, and read from file
        $folder = $download_gedcom_with_URL->getPreference(DownloadGedcomWithURL::PREF_FOLDER_TO_SAVE, '');
        $root_filesystem = Registry::filesystem()->root();
        $server_file = $folder . $file_name . '.ged';

        try {
            $resource = $root_filesystem->readStream($server_file);
        }
        catch (Throwable $ex) {
            $message = I18N::translate('Unable to read file "%s".', $server_file);
            return $download_gedcom_with_URL->showErrorMessage($message);
        }        

        //Import the file to a set of Gedcom records
        try {
            $stream = $this->stream_factory->createStreamFromResource($resource);
            $gedcom_records = $this->importGedcomFile($tree, $stream, $server_file, $encoding);

            $message = I18N::translate('The file "%s" was sucessfully uploaded for the family tree "%s"', $file_name . '.ged', $tree->name());
            FlashMessages::addMessage($message, 'success');
        }
        catch (Throwable $ex) {
            return $download_gedcom_with_URL->showErrorMessage($ex->getMessage());
        }      

        //Apply export filters
        $matched_tag_combinations = [];
        $export_filters = [];
        $gedcom_records = $gedcom_export_filter_service->applyExportFilters($gedcom_records, $export_filters, $matched_tag_combinations, $tree);
        
        if ($gedcom_conversion) {
            //Create a response from the filtered data
            $response = $gedcom_export_filter_service->filteredDownloadResponse(
                $tree, true, $encoding, $privacy, $line_endings, $file_name, $format, [], new Collection($gedcom_records));
    
            //Download the data
            return $response;    
        }

        //Create a stream from the filtered data
        $resource = $gedcom_export_filter_service->filteredSaveResponse(
            $tree, true, $encoding, $privacy, $line_endings, $format, [], new Collection($gedcom_records));
        $stream = $this->stream_factory->createStreamFromResource($resource);

        //Import the stream into the database
        try {
            $this->tree_service->importGedcomFile($tree, $stream, $server_file, $encoding);
        }
        catch (Throwable $ex) {
            return $download_gedcom_with_URL->showErrorMessage($ex->getMessage());
        }      

        //Redirect in order to process the Gedcom data of the imported file
        return redirect(route(ManageTrees::class, ['tree' => $tree->name()]));
    }
}
