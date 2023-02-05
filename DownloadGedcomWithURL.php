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
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Encodings\ANSEL;
use Fisharebest\Webtrees\Encodings\ASCII;
use Fisharebest\Webtrees\Encodings\UTF16BE;
use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\Encodings\Windows1252;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Html;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToWriteFile;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function substr;


class DownloadGedcomWithURL extends AbstractModule implements 
	ModuleCustomInterface, 
	ModuleConfigInterface,
	RequestHandlerInterface 
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;
 
    private GedcomExportService $gedcom_export_service;

    private GedcomSevenExportService $gedcom7_export_service;

    private Tree $download_tree;

	//Custom module version
	public const CUSTOM_VERSION = '3.0.1';

	//Route
	protected const ROUTE_URL = '/DownloadGedcomWithURL'; 

	//Github repository
	public const GITHUB_REPO = 'Jefferson49/DownloadGedcomWithURL';

	//Github API URL to get the information about the latest releases
	public const GITHUB_API_LATEST_VERSION = 'https://api.github.com/repos/'. self::GITHUB_REPO . '/releases/latest';
	public const GITHUB_API_TAG_NAME_PREFIX = '"tag_name":"v';

	//Author of custom module
	public const CUSTOM_AUTHOR = 'Markus Hemprich';

    //Prefences, Settings
	private const PREF_SECRET_KEY = "secret_key";


   /**
     * DownloadGedcomWithURL constructor.
     */
    public function __construct()
    {
	    $response_factory = app(ResponseFactoryInterface::class);
        $stream_factory = new Psr17Factory();

        $this->gedcom_export_service = new GedcomExportService($response_factory, $stream_factory);
        $this->gedcom7_export_service = new GedcomSevenExportService($response_factory, $stream_factory);
    }

    /**
     * Initialization.
     *
     * @return void
     */
    public function boot(): void
    {
        Registry::routeFactory()->routeMap()
            ->get(static::class, self::ROUTE_URL, $this)
            ->allows(RequestMethodInterface::METHOD_POST);

		// Register a namespace for the views.
		View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }
	
    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::title()
     */
    public function title(): string
    {
        return 'DownloadGedcomWithURL';
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::description()
     */
    public function description(): string
    {
        /* I18N: Description of the “AncestorsChart” module */
        return I18N::translate('A custom module to download GEDCOM files on URL requests with the tree name, GEDCOM file name, and authorization provided as parameters within the URL.');
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::resourcesFolder()
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleAuthorName()
     */
    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleVersion()
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleLatestVersion()
     */
    public function customModuleLatestVersion(): string
    {
        // No update URL provided.
        if (self::GITHUB_API_LATEST_VERSION === '') {
            return $this->customModuleVersion();
        }
        return Registry::cache()->file()->remember(
            $this->name() . '-latest-version',
            function (): string {
                try {
                    $client = new Client(
                        [
                        'timeout' => 3,
                        ]
                    );

                    $response = $client->get(self::GITHUB_API_LATEST_VERSION);

                    if ($response->getStatusCode() === StatusCodeInterface::STATUS_OK) {
                        $content = $response->getBody()->getContents();
                        preg_match_all('/' . self::GITHUB_API_TAG_NAME_PREFIX . '\d+\.\d+\.\d+/', $content, $matches, PREG_OFFSET_CAPTURE);

						if(!empty($matches[0]))
						{
							$version = $matches[0][0][0];
							$version = substr($version, strlen(self::GITHUB_API_TAG_NAME_PREFIX));	
						}
						else
						{
							$version = $this->customModuleVersion();
						}

                        return $version;
                    }
                } catch (GuzzleException $ex) {
                    // Can't connect to the server?
                }

                return $this->customModuleVersion();
            },
            86400
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleSupportUrl()
     */
    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/' . self::GITHUB_REPO;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $language
     *
     * @return array
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customTranslations()
     */
    public function customTranslations(string $language): array
    {
        $lang_dir   = $this->resourcesFolder() . 'lang/';
        $file       = $lang_dir . $language . '.mo';
        if (file_exists($file)) {
            return (new Translation($file))->asArray();
        } else {
            return [];
        }
    }

    /**
     * View module settings in control panel
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        return $this->viewResponse(
            $this->name() . '::settings',
            [
                'title'                 => $this->title(),
				self::PREF_SECRET_KEY   => $this->getPreference(self::PREF_SECRET_KEY, ''),
            ]
        );
    }

    /**
     * Save module settings after returning from control panel
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();

        //Save the received settings to the user preferences
        if ($params['save'] === '1') {

			//If provided secret key is too short
			if(strlen($params[self::PREF_SECRET_KEY])<8) {
				$message = I18N::translate('The provided secret key is too short. Please provide a minimum length of 8 characters.', $this->title());
				FlashMessages::addMessage($message, 'danger');				
			}
			//If secret key does not escape correctly
			elseif($params[self::PREF_SECRET_KEY] !== e($params[self::PREF_SECRET_KEY])) {
				$message = I18N::translate('The provided secret key contains characters, which are not accepted. Please provide a different key.', $this->title());
				FlashMessages::addMessage($message, 'danger');				
			}
			else {
				$this->setPreference(self::PREF_SECRET_KEY, isset($params[self::PREF_SECRET_KEY]) ? $params[self::PREF_SECRET_KEY] : '');

				$message = I18N::translate('The preferences for the module "%s" were updated.', $this->title());
				FlashMessages::addMessage($message, 'success');	
			}
        }

        return redirect($this->getConfigLink());
    }

    /**
     * All the trees that the current user has permission to access.
     *
     * @return Collection<array-key,Tree>
     */
    public function all(): Collection
    {
        return Registry::cache()->array()->remember('all-trees', static function (): Collection {
            // All trees
            $query = DB::table('gedcom')
                ->leftJoin('gedcom_setting', static function (JoinClause $join): void {
                    $join->on('gedcom_setting.gedcom_id', '=', 'gedcom.gedcom_id')
                        ->where('gedcom_setting.setting_name', '=', 'title');
                })
                ->where('gedcom.gedcom_id', '>', 0)
                ->select([
                    'gedcom.gedcom_id AS tree_id',
                    'gedcom.gedcom_name AS tree_name',
                    'gedcom_setting.setting_value AS tree_title',
                ])
                ->orderBy('gedcom.sort_order')
                ->orderBy('gedcom_setting.setting_value');

            return $query
                ->get()
                ->mapWithKeys(static function (object $row): array {
                    return [$row->tree_name => Tree::rowMapper()($row)];
                });
        });
    }
     /**
     * Check if tree is a valid tree
     *
     * @return bool
     */ 
     private function isValidTree(string $tree_name): bool
	 {
		$find_tree = $this->all()->first(static function (Tree $tree) use ($tree_name): bool {
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
			'module_name'	=> $this->title(),
			'text'  	   	=> $text,
		]);	 
	 }
 
     /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */	
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
		//Load secret key from preferences
        $secret_key = $this->getPreference(self::PREF_SECRET_KEY, ''); 
   		
		$tree_name    = Validator::queryParams($request)->string('tree', '');
        $file_name    = Validator::queryParams($request)->string('file', $tree_name);
        $format       = Validator::queryParams($request)->string('format', 'gedcom');
        $privacy      = Validator::queryParams($request)->string('privacy', 'visitor');
        $encoding     = Validator::queryParams($request)->string('encoding', UTF8::NAME);
        $line_endings = Validator::queryParams($request)->string('line_endings', 'CRLF');
		$gedcom7      = Validator::queryParams($request)->boolean('gedcom7', false);
		$gedcom_l     = Validator::queryParams($request)->boolean('gedcom_l', false);
		$action       = Validator::queryParams($request)->string('action', 'download');
		$time_stamp   = Validator::queryParams($request)->string('time_stamp', '');
		$key          = Validator::queryParams($request)->string('key', '');

        //Error if tree name is not valid
        if (!$this->isValidTree($tree_name)) {
			$response = $this->showErrorMessage(I18N::translate('Tree not found') . ': ' . $tree_name);
		}
        //Error if key is empty
        elseif ($key === '') {
			$response = $this->showErrorMessage(I18N::translate('No key provided. For checking of the access rights, it is mandatory to provide a key as parameter in the URL.'));
		}
		//Error if secret key is empty
        elseif ($secret_key === '') {
			$response = $this->showErrorMessage(I18N::translate('No secret key defined. Please define secret key in the module settings: Control Panel / Modules / All Modules / ' . $this->title()));
		}
		//Error if key is not valid
        elseif ($key !== $secret_key) {
			$response = $this->showErrorMessage(I18N::translate('Key not accepted. Access denied.'));
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
        //Error action is not valid
        elseif (!in_array($action, ['download', 'save', 'both'])) {
			$response = $this->showErrorMessage(I18N::translate('Action not accepted') . ': ' . $action);
        }  
		//Error if line ending is not valid
        elseif (!in_array($line_endings, ['CRLF', 'LF'])) {
			$response = $this->showErrorMessage(I18N::translate('Line endings not accepted') . ': ' . $line_endings);
        } 
		//Error if time_stamp is not valid
        elseif (!in_array($time_stamp, ['prefix', 'postfix', ''])) {
			$response = $this->showErrorMessage(I18N::translate('Time stamp setting not accepted') . ': ' . $time_stamp);
        } 	

		else {
			//Add time stamp to file name if requested
			if($time_stamp === 'prefix'){
				$file_name = date('Y-m-d_H-i-s_') . $file_name;
			} 
			elseif($time_stamp === 'postfix'){
				$file_name .= date('_Y-m-d_H-i-s');
			}

			//Save or both
			if (($action === 'save') or ($action === 'both')) {

				$data_filesystem = Registry::filesystem()->data();
				$access_level = GedcomSevenExportService::ACCESS_LEVELS[$privacy];

				// Force a ".ged" suffix
				if (strtolower(pathinfo($file_name, PATHINFO_EXTENSION)) !== 'ged') {
					$file_name .= '.ged';
				}

				//If Gedcom 7, create Gedcom 7 response
				if (($format === 'gedcom') && ($gedcom7)) {
					try {
						$resource = $this->gedcom7_export_service->export($this->download_tree, true, $encoding, $access_level, $line_endings, $gedcom7);
						$data_filesystem->writeStream($file_name, $resource);
						fclose($resource);

						/* I18N: %s is a filename */
						FlashMessages::addMessage(I18N::translate('The family tree has been exported to %s.', Html::filename($file_name)), 'success');

					} catch (FilesystemException | UnableToWriteFile $ex) {
						FlashMessages::addMessage(
							I18N::translate('The file %s could not be created.', Html::filename($file_name)) . '<hr><samp dir="ltr">' . $ex->getMessage() . '</samp>',
							'danger'
						);
					}

				}
				//Create Gedcom 5.5.1 response
				else {
					try {
						$resource = $this->gedcom_export_service->export($this->download_tree, true, $encoding, $access_level, $line_endings);
						$data_filesystem->writeStream($file_name, $resource);
						fclose($resource);

						/* I18N: %s is a filename */
						FlashMessages::addMessage(I18N::translate('The family tree has been exported to %s.', Html::filename($file_name)), 'success');

					} catch (FilesystemException | UnableToWriteFile $ex) {
						FlashMessages::addMessage(
							I18N::translate('The file %s could not be created.', Html::filename($file_name)) . '<hr><samp dir="ltr">' . $ex->getMessage() . '</samp>',
							'danger'
						);
					}
				}

				$response = response('Successfully saved GEDCOM file to local file system');
			}

			//If download
			if ($action === 'download') {

				//If Gedcom 7, create Gedcom 7 response
				if (($format === 'gedcom') && ($gedcom7)) {

					$response = $this->gedcom7_export_service->downloadGedcomSevenresponse($this->download_tree, true, $encoding, $privacy, $line_endings, $file_name, $format, $gedcom_l);
				}
				//Create Gedcom 5.5.1 response
				else {
					$response = $this->gedcom_export_service->downloadResponse($this->download_tree, true, $encoding, $privacy, $line_endings, $file_name, $format);
				}
			}
		}

		return $response;
		
    }
}

