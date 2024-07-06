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

use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Http\RequestHandlers\ManageTrees;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToReadFile;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * Remotely import a GEDCOM file into a tree.
 */
class RemoteImportGedcomAction implements RequestHandlerInterface
{
    private StreamFactoryInterface $stream_factory;

    private TreeService $tree_service;

    private ModuleService $module_service;

    /**
     * @param StreamFactoryInterface $stream_factory
     * @param TreeService            $tree_service
     */
    public function __construct()
    {
        $this->tree_service   = new TreeService(new GedcomImportService);
        $this->stream_factory = new Psr17Factory();
        $this->module_service = new ModuleService();
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
        $tree_name          = Validator::queryParams($request)->string('tree');
        $file_name          = Validator::queryParams($request)->string('file');
        $encoding           = 'UTF-8';
   
        //Get tree 
        $tree = $this->tree_service->all()[$tree_name];
        assert($tree instanceof Tree);

        //Get folder from module settings and create server file name
        $download_gedcom_with_URL = $this->module_service->findByName(DownloadGedcomWithURL::activeModuleName());

        $folder = $download_gedcom_with_URL->getPreference(DownloadGedcomWithURL::PREF_FOLDER_TO_SAVE, '');
        $root_filesystem = Registry::filesystem()->root();
        $server_file = $folder . $file_name . '.ged';


        try {
            $resource = $root_filesystem->readStream($server_file);
            $stream   = $this->stream_factory->createStreamFromResource($resource);
            $this->tree_service->importGedcomFile($tree, $stream, $server_file, $encoding);

            $message = I18N::translate('The file "%s" was sucessfully uploaded for the family tree "%s"', $file_name . '.ged', $tree->name());
            FlashMessages::addMessage($message, 'success');
        }
        catch (DownloadGedcomWithUrlException $ex) {

            return $response = $download_gedcom_with_URL->showErrorMessage($ex->getMessage());
        }        

        return redirect(route(ManageTrees::class, ['tree' => $tree->name()]));        
    }
}
