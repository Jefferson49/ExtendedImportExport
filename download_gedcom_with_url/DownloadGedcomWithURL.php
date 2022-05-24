<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2022 webtrees development team
 * Copyright (C) 2022 Webmaster @ Familienforschung Hemprich, 
 *                    <http://www.familienforschung-hemprich.de>
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
 *
 * DownloadGedcomWithURL
 *
 * A weebtrees(https://webtrees.net) 2.1 custom module to download GEDCOM files on URL requests 
 * with the tree name, GEDCOM file name and authorization provided as parameters within the URL.
 * 
 * Example URL:
 * http://MY_URL/webtrees/index.php?route=/webtrees/DownloadGedcomWithURL/tree/MY_TREE/filename/MY_FILENAME/privacy/MY_PRIVACY_LEVEL
 *
 * MY_TREE specifies the webtrees tree name
 *
 * M>_FILENAME has to be provided without .ged extension
 * i.e. use my_file instead of my_file.ged
 *
 * For MY_PRIVACY_LEVEL, the following values can be used
 *  	gedadmin
 * 		user 
 * 		visitor  
 *		none     (Default)
 *
 * Note:
 * The Gedcom file will always be downloaded from the last tree, which was used
 * in the frontend.
 * 
 * IMPORTANT SECURITY NOTE:
 * Please note that installing this module will enable everyone who can reach the
 * webtrees URL to download the GEDCOM files from webtrees. This even works if no user
 * is logged in. Therefore, you should only consider to use this module in private 
 * networks etc.
 *
 */

 
declare(strict_types=1);

namespace DownloadGedcomWithURLNamespace;

use Fisharebest\Localization\Locale;
use Fisharebest\Localization\Locale\LocaleInterface;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\LanguageEnglishUnitedStates;
use Fisharebest\Webtrees\Module\LanguageGerman;
use Fisharebest\Webtrees\Module\ModuleLanguageInterface;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Site;
use Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function str_contains;

//For Gedcom export
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use League\Flysystem\Filesystem;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;
use function addcslashes;
use function app;
use function assert;
use function fclose;
use function fopen;
use function pathinfo;
use function rewind;
use function strtolower;
use function tmpfile;

//TreeService
use Fisharebest\Webtrees\Services\TreeService;



class DownloadGedcomWithURL extends AbstractModule implements ModuleCustomInterface, MiddlewareInterface {

    use ModuleCustomTrait;
  
    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        return 'DownloadGedcomWithURL module';
    }

    /**
     * Code here is executed before and after we process the request/response.
     * We can block access by throwing an exception.
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Code here is executed before we process the request/response.
	
		$params = $request->getQueryParams();
		$download_file_requested = $params['downloadgedcom'] ?? '';
		
		if ($download_file_requested !== '') {

			$tree_id = $params['treeid'] ?? '';
			$access_level_requested = $params['accesslevel'] ?? '';

			$tree_service = new TreeService();
			$tree = $tree_service->find(intval($tree_id));
			assert($tree instanceof Tree);

			$data_filesystem = Registry::filesystem()->data();

			$params = (array) $request->getParsedBody();

			$convert          = (bool) ($params['convert'] ?? false);
			$media_path       = $params['media-path'] ?? '';

			$access_levels = [
				'gedadmin' => Auth::PRIV_NONE,
				'user'     => Auth::PRIV_USER,
				'visitor'  => Auth::PRIV_PRIVATE,
				'none'     => Auth::PRIV_HIDE,
			];

			$encoding     = $convert ? 'ANSI' : 'UTF-8';

			// How to call the downloaded file
			$download_filename = $download_file_requested;

			// Add .ged extension
			$download_filename .= '.ged';
			
			
			// Which access level
			if ($access_level_requested === 'gedadmin') {
				$access_level = Auth::PRIV_NONE; 
			}
			else if ($access_level_requested === 'user') {
				$access_level = Auth::PRIV_USER; 

			}
			else if ($access_level_requested === 'visitor') {
				$access_level = Auth::PRIV_PRIVATE; 

			}
			else {
				//Default is 'none'
				$access_level = Auth::PRIV_HIDE; 
			}
		
			$resource = fopen('php://temp', 'wb+');

			if ($resource === false) {
				throw new RuntimeException('Failed to create temporary stream');
			}

			$gedcom_export_service = new GedcomExportService();
		
			$gedcom_export_service->export($tree, $resource, true, $encoding, $access_level, $media_path);
			rewind($resource);

			$charset = $convert ? 'ISO-8859-1' : 'UTF-8';

			$stream_factory = app(StreamFactoryInterface::class);
			assert($stream_factory instanceof StreamFactoryInterface);

			$http_stream = $stream_factory->createStreamFromResource($resource);

			/** @var ResponseFactoryInterface $response_factory */
			$response_factory = app(ResponseFactoryInterface::class);

			return $response_factory->createResponse()
				->withBody($http_stream)
				->withHeader('Content-Type', 'text/x-gedcom; charset=' . $charset)
				->withHeader('Content-Disposition', 'attachment; filename="' . addcslashes($download_filename, '"') . '"');
		}

					
        // Generate the response.
        return $handler->handle($request);	
	
    }
}
