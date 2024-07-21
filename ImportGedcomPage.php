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
 *
 * 
 * DownloadGedcomWithURL
 *
 * A weebtrees(https://webtrees.net) 2.1 custom module to download or store GEDCOM files on URL requests 
 * with the tree name, GEDCOM file name and authorization provided as parameters within the URL.
 * 
 */

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\AdminService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Module\DownloadGedcomWithURL\DownloadGedcomWithURL;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function e;

/**
 * Import a GEDCOM file into a tree.
 */
class ImportGedcomPage implements RequestHandlerInterface
{
    use ViewResponseTrait;

    private AdminService $admin_service;

    /**
     * @param AdminService $admin_service
     */
    public function __construct(AdminService $admin_service)
    {
        $this->admin_service = $admin_service;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        $default_tree_name       = Validator::queryParams($request)->string('default_tree_name');
        $default_gedcom_file     = Validator::queryParams($request)->string('default_gedcom_file');
        $gedcom_media_path       = Validator::queryParams($request)->string('gedcom_media_path');
        $default_gedcom_filter1  = Validator::queryParams($request)->string('default_gedcom_filter1', I18N::translate('None'));
        $default_gedcom_filter2  = Validator::queryParams($request)->string('default_gedcom_filter2', I18N::translate('None'));
        $default_gedcom_filter2  = Validator::queryParams($request)->string('default_gedcom_filter3', I18N::translate('None'));

        $data_filesystem = Registry::filesystem()->data();
        $data_folder     = Registry::filesystem()->dataName();

        $gedcom_files        = $this->admin_service->gedcomFiles($data_filesystem);

        $module_service = new ModuleService();
        $download_gedcom_with_url = $module_service->findByName(DownloadGedcomWithURL::activeModuleName());

        //Load export filters
        try {
            DownloadGedcomWithURL::loadGedcomFilterClasses();
        }
        catch (DownloadGedcomWithUrlException $ex) {
            FlashMessages::addMessage($ex->getMessage(), 'danger');
        }

        $gedcom_filter_list = $download_gedcom_with_url->getGedcomFilterList();
        $tree_list = $download_gedcom_with_url->getTreeNameTitleList();
        $control_panel_secret_key= $download_gedcom_with_url->getPreference(DownloadGedcomWithURL::PREF_CONTROL_PANEL_SECRET_KEY, '');

        return $this->viewResponse(
            DownloadGedcomWithURL::viewsNamespace() . '::import',
            [
                'title'                    => I18N::translate('Extended GEDCOM Import'),
                'data_folder'              => $data_folder,
                'default_gedcom_file'      => $default_gedcom_file,
                'gedcom_files'             => $gedcom_files,
                'gedcom_media_path'        => $gedcom_media_path,
                'default_tree_name'        => $default_tree_name,
                'tree_list'                => $tree_list,
                'control_panel_secret_key' => $control_panel_secret_key,
                'gedcom_filter_list'       => $gedcom_filter_list,
                'default_gedcom_filter1'   => $default_gedcom_filter1,
                'default_gedcom_filter2'   => $default_gedcom_filter2,
                'default_gedcom_filter3'   => $default_gedcom_filter2,
            ]
        );
    }
}
