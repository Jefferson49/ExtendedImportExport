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
 * Github repository: https://github.com/Jefferson49/DownloadGedcomWithURL
 *
 * A weebtrees(https://webtrees.net) 2.1 custom module to download GEDCOM files on URL requests 
 * with the tree name, GEDCOM file name and authorization provided as parameters within the URL.
 * 
 */

declare(strict_types=1);

namespace DownloadGedcomWithURLNamespace;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\Encodings\UTF16BE;
use Fisharebest\Webtrees\Encodings\ANSEL;
use Fisharebest\Webtrees\Encodings\ASCII;
use Fisharebest\Webtrees\Encodings\Windows1252;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function route;


class DownloadGedcomWithURL extends AbstractModule implements ModuleCustomInterface, RequestHandlerInterface {

    use ModuleCustomTrait;
 
    protected const ROUTE_URL = '/DownloadGedcomWithURL'; 

    private GedcomExportService $gedcom_export_service;

    private Tree $download_tree;

   /**
     * DownloadGedcomWithURL constructor.
     */
    public function __construct()
    {
	    $response_factory = app(ResponseFactoryInterface::class);
        $stream_factory = new Psr17Factory();

        $this->gedcom_export_service = new GedcomExportService($response_factory, $stream_factory);
    }

    /**
     * Initialization.
     *
     * @return void
     */
    public function boot(): void
    {
        Registry::routeFactory()->routeMap()
            ->get(static::class, static::ROUTE_URL, $this)
            ->allows(RequestMethodInterface::METHOD_POST);

		// Register a namespace for our views.
		View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }
	
    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */	 
    public function title(): string
    {
        return 'DownloadGedcomWithURL custom module';
    }

	/**
     * Where does this module store its resources
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
		return __DIR__ . '/resources/';
    }

     /**
     * Check if tree is a valid tree
     *
     * @return bool
     */ 
     private function isValidTree(string $tree_name): bool
	 {		 
		$tree_service = new TreeService(new GedcomImportService);
		
		$find_tree = $tree_service->all()->first(static function (Tree $tree) use ($tree_name): bool {
            return $tree->name() === $tree_name;
        });
		
		$is_valid_tree = $find_tree instanceof Tree;
		
		if ($is_valid_tree) {
            $this->download_tree = $find_tree;
        }
		
		return $is_valid_tree;
	 }
	 
	 /**
     * Show error message in the front end
     *
     * @return ResponseInterface
     */ 
     private function showErrorMessage(string $text): ResponseInterface
	 {		
		return $this->viewResponse($this->name() . '::error', [
            'title'        	=> 'Error',
			'tree'			=> null,
			'text'  	   	=> I18N::translate('Custom module') . ': ' . $this->name() . '<br><b>'. $text . '</b>',
		]);	 
	 }
 
     /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */	
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        
        if ($tree === null) {
            $default_tree_name = '';
        }
        else {
            $default_tree_name = $tree->name();
        }
   		
		$params = $request->getQueryParams();
		$tree_name = $params['tree'] ?? $default_tree_name;	
		$file_name = $params['file'] ?? $tree_name;	
		$privacy = $params['privacy'] ?? 'none';	
		$format = $params['format'] ?? 'gedcom';
		$encoding = $params['encoding'] ?? UTF8::NAME;
		$line_endings = $params['line_endings'] ?? 'CRLF';

        //Take tree name if file name is empty 
        if ($file_name == '') {
			$file_name = $tree_name;
		}   

        //Error if tree name is not valid
        if (!$this->isValidTree($tree_name)) {
			$response = $this->showErrorMessage(I18N::translate('Tree not found') . ': ' . $tree_name);
		}
        //Error if privacy level is not valid
		elseif (!in_array($privacy, ['none', 'gedadmin', 'user', 'visitor'])) {
			$response = $this->showErrorMessage(I18N::translate('Privacy level not accepted') . ': ' . $privacy);
        }
        //Error if export format is not valid
        elseif (!in_array($format, ['gedcom', 'zip', 'zipmedia', 'gedzip'])) {
			$response = $this->showErrorMessage(I18N::translate('Export format not accepted') . ': ' . $format);
        }       
        //Error if encoding is not valid
		elseif (!in_array($encoding, [UTF8::NAME, UTF16BE::NAME, ANSEL::NAME, ASCII::NAME, Windows1252::NAME])) {
			$response = $this->showErrorMessage(I18N::translate('Encoding not accepted') . ': ' . $encoding);
        }       
        //Error if line ending is not valid
        elseif (!in_array($line_endings, ['CRLF', 'LF'])) {
			$response = $this->showErrorMessage(I18N::translate('Line endings not accepted') . ': ' . $line_endings);
        }       
		//Create response to download GEDCOM file
        else {
            $response = $this->gedcom_export_service->downloadResponse($this->download_tree, true, $encoding, $privacy, $line_endings, $file_name, $format); 
        }

        return $response;
    }
}

