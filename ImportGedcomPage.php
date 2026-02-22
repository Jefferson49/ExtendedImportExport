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
 * Copyright (C) 2025 Markus Hemprich
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
use Fisharebest\Webtrees\Http\RequestHandlers\HomePage;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\AdminService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Internationalization\MoreI18N;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;


/**
 * Import a GEDCOM file into a tree.
 */
class ImportGedcomPage implements RequestHandlerInterface
{
    use ViewResponseTrait;

    private AdminService  $admin_service;
    private ModuleService $module_service;
    private TreeService   $tree_service;


    public function __construct(AdminService $admin_service, ModuleService $module_service, TreeService $tree_service)
    {
        $this->admin_service  = $admin_service;
        $this->module_service = $module_service;
        $this->tree_service   = $tree_service;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        $tree_name = Validator::queryParams($request)->string('tree', '');
        $tree      = $this->tree_service->all()[$tree_name] ?? null;

        /** @var DownloadGedcomWithURL $download_gedcom_with_url */
        $download_gedcom_with_url = $this->module_service->findByName(DownloadGedcomWithURL::activeModuleName());

        //If current user is no admin, return to the home page
        if (!Auth::isAdmin()) { 
            FlashMessages::addMessage(I18N::translate('Access denied. The user needs to be an administrator.'), 'danger');
            return redirect(route(HomePage::class));
        }

        //If no tree access, return to the home page
        if ($tree === null) {
            FlashMessages::addMessage(I18N::translate('The current user does not have sufficient rights to access trees with the custom module %s.', $download_gedcom_with_url->title()), 'danger');	
            return redirect(route(HomePage::class));
        }

        $gedcom_filename = Validator::queryParams($request)->string('gedcom_filename', $tree->getPreference('gedcom_filename'));
        $gedcom_filter1  = Validator::queryParams($request)->string('gedcom_filter1', MoreI18N::xlate('None'));
        $gedcom_filter2  = Validator::queryParams($request)->string('gedcom_filter2', MoreI18N::xlate('None'));
        $gedcom_filter3  = Validator::queryParams($request)->string('gedcom_filter3', MoreI18N::xlate('None'));

        $folder          = $download_gedcom_with_url->getPreference(DownloadGedcomWithURL::PREF_FOLDER_TO_SAVE, '');
        $data_filesystem = Registry::filesystem()->root($folder);
        $gedcom_files    = $this->admin_service->gedcomFiles($data_filesystem);

        //Load Gedcom filters
        try {
            DownloadGedcomWithURL::loadGedcomFilterClasses();
        }
        catch (DownloadGedcomWithUrlException $ex) {
            FlashMessages::addMessage($ex->getMessage(), 'danger');
        }

        $gedcom_filter_list = $download_gedcom_with_url->getGedcomFilterList();

        return $this->viewResponse(
            DownloadGedcomWithURL::viewsNamespace() . '::import',
            [
                'title'               => I18N::translate('Extended GEDCOM Import') . ' — ' . e($tree->title()),
                'tree'                => $tree,
                'folder'              => $folder,
                'gedcom_files'        => $gedcom_files,
                'gedcom_filename'     => $gedcom_filename,
                'gedcom_filter_list'  => $gedcom_filter_list,
                'gedcom_filter1'      => $gedcom_filter1,
                'gedcom_filter2'      => $gedcom_filter2,
                'gedcom_filter3'      => $gedcom_filter3,
            ]
        );
    }
}
