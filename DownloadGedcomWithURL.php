<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2023 webtrees development team
 *                    <http://webtrees.net>
 *
 * Fancy Research Links (webtrees custom module):
 * Copyright (C) 2022 Carmen Just
 *                    <https://justcarmen.nl>
 *
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

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\Encodings\ANSEL;
use Fisharebest\Webtrees\Encodings\ASCII;
use Fisharebest\Webtrees\Encodings\UTF16BE;
use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\Encodings\Windows1252;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Session;
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
	public const CUSTOM_VERSION = '3.1.0';

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
	public const PREF_MODULE_VERSION = 'module_version';
	public const PREF_SECRET_KEY = "secret_key";
	public const PREF_USE_HASH = "use_hash";
	public const PREF_ALLOW_DOWNLOAD = "allow_download";
	public const PREF_FOLDER_TO_SAVE = "folder_to_save";

	//Alert tpyes
	public const ALERT_DANGER = 'alert_danger';
	public const ALERT_SUCCESS = 'alert_success';

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
                'title'                       => $this->title(),
				self::PREF_SECRET_KEY         => $this->getPreference(self::PREF_SECRET_KEY, ''),
				self::PREF_USE_HASH           => boolval($this->getPreference(self::PREF_USE_HASH, '1')),
				self::PREF_ALLOW_DOWNLOAD     => boolval($this->getPreference(self::PREF_ALLOW_DOWNLOAD, '1')),
				self::PREF_FOLDER_TO_SAVE     => $this->getPreference(self::PREF_FOLDER_TO_SAVE, ''),
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
        $save                = Validator::parsedBody($request)->string('save', '');
        $use_hash            = Validator::parsedBody($request)->boolean(self::PREF_USE_HASH, false);
        $allow_download      = Validator::parsedBody($request)->boolean(self::PREF_ALLOW_DOWNLOAD, false);
        $new_secret_key      = Validator::parsedBody($request)->string('new_secret_key', '');
        $folder_to_save      = Validator::parsedBody($request)->string(self::PREF_FOLDER_TO_SAVE, '');

        //Save the received settings to the user preferences
        if ($save === '1') {

			if($new_secret_key === '') {
				//If use hash changed from true to false, reset key (hash cannot be used any more)
				if(boolval($this->getPreference(self::PREF_USE_HASH, '0')) && !$use_hash) {
					$this->setPreference(self::PREF_SECRET_KEY, '');
				}
				//If use hash changed from false to true, take old key (for planned encryption)
				elseif(!boolval($this->getPreference(self::PREF_USE_HASH, '0')) && $use_hash) {
					$new_secret_key = $this->getPreference(self::PREF_SECRET_KEY, '');
				}
			}
			//If provided secret key is too short
			elseif(strlen($new_secret_key)<8) {
				$message = I18N::translate('The provided secret key is too short. Please provide a minimum length of 8 characters.');
				FlashMessages::addMessage($message, 'danger');				
			}
			//If secret key does not escape correctly
			elseif($new_secret_key !== e($new_secret_key)) {
				$message = I18N::translate('The provided secret key contains characters, which are not accepted. Please provide a different key.');
				FlashMessages::addMessage($message, 'danger');				
			}
			//If secret key be stored with a hash
			elseif($use_hash) {

				$hash_value = password_hash($new_secret_key, PASSWORD_BCRYPT);
				$this->setPreference(self::PREF_SECRET_KEY, $hash_value);
			}
			else {
				$this->setPreference(self::PREF_SECRET_KEY, $new_secret_key);
			}

			//Set 
			if (!str_ends_with($folder_to_save, '/')) {
				$folder_to_save .= '/';
			}
	
			if (is_dir($folder_to_save)) {
				$this->setPreference(self::PREF_FOLDER_TO_SAVE, $folder_to_save);
			} else {
				FlashMessages::addMessage(I18N::translate('The folder settings could not be saved, because the folder “%s” does not exist.', e($folder_to_save)), 'danger');
			}

			$this->setPreference(self::PREF_USE_HASH, $use_hash ? '1' : '0');
			$this->setPreference(self::PREF_ALLOW_DOWNLOAD, $allow_download ? '1' : '0');

			$message = I18N::translate('The preferences for the module "%s" were updated.', $this->title());
			FlashMessages::addMessage($message, 'success');	
		}

        return redirect($this->getConfigLink());
    }

	/**
     * Update the preferences (after new module version is detected)
     *
     * @return string
     */
    public function updatePreferences(): string
    {
 		//If no module version is stored yet (i.e. before version v3.0.1)
		if($this->getPreference(self::PREF_MODULE_VERSION, '') === '') {

			//Set secret key hashing to false
			$this->setPreference(self::PREF_USE_HASH, '0');
		}

        $error = '';
        return $error;
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
		return $this->viewResponse($this->name() . '::alert', [
            'title'        	=> 'Error',
			'tree'			=> null,
			'alert_type'    => DownloadGedcomWithURL::ALERT_DANGER,
			'module_name'	=> $this->title(),
			'text'  	   	=> $text,
		]);	 
	 }
 
	 /**
     * Show success message in the front end
     *
     * @return ResponseInterface
     */ 
	private function showSuccessMessage(string $text): ResponseInterface
	{		
	   return $this->viewResponse($this->name() . '::alert', [
		   'title'        	=> 'Success',
		   'tree'			=> null,
		   'alert_type'     => DownloadGedcomWithURL::ALERT_SUCCESS,
		   'module_name'	=> $this->title(),
		   'text'  	   	    => $text,
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

		//Check module version
		if ($this->getPreference(self::PREF_MODULE_VERSION) !== self::CUSTOM_VERSION) {

			//Update prefences stored in database
			$update_result = $this->updatePreferences();

			//If error during update of preferences, show error message
			if ($update_result !== '') {
				return $this->showErrorMessage(I18N::translate('Error during update of the module preferences') . ': ' . $update_result);
			}
			else {
				$this->setPreference(self::PREF_MODULE_VERSION, self::CUSTOM_VERSION);
			}
		}
		
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
		//Error if no hashing and key is not valid
        elseif (!boolval($this->getPreference(self::PREF_USE_HASH, '0')) &&($key !== $secret_key)) {
			$response = $this->showErrorMessage(I18N::translate('Key not accepted. Access denied.'));
		}
		//Error if hashing and key does not fit to hash
        elseif (boolval($this->getPreference(self::PREF_USE_HASH, '0')) &&(!password_verify($key, $secret_key))) {
			$response = $this->showErrorMessage(I18N::translate('Key (encrypted) not accepted. Access denied.'));
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

		//If no errors, start the core activities of the module
		else {

			//Add time stamp to file name if requested
			if($time_stamp === 'prefix'){
				$file_name = date('Y-m-d_H-i-s_') . $file_name;
			} 
			elseif($time_stamp === 'postfix'){
				$file_name .= date('_Y-m-d_H-i-s');
			}

			//If save or both
			if (($action === 'save') or ($action === 'both')) {

				$root_filesystem = Registry::filesystem()->root();
				$access_level = GedcomSevenExportService::ACCESS_LEVELS[$privacy];
				$export_file_name = $file_name;

				// Force a ".ged" suffix
				if (strtolower(pathinfo($export_file_name, PATHINFO_EXTENSION)) !== 'ged') {
					$export_file_name .= '.ged';
				}

				//Get folder from settings
				$folder_to_save = $this->getPreference(self::PREF_FOLDER_TO_SAVE, '');

				//If Gedcom 7, create Gedcom 7 response
				if (($format === 'gedcom') && ($gedcom7)) {
					try {
						$resource = $this->gedcom7_export_service->export($this->download_tree, true, $encoding, $access_level, $line_endings, $gedcom7);
						$root_filesystem->writeStream($folder_to_save . $export_file_name, $resource);
						fclose($resource);

						$response = $this->showSuccessMessage(I18N::translate('The family tree "%s" has been exported to: %s', $tree_name, $folder_to_save . $export_file_name));

					} catch (FilesystemException | UnableToWriteFile $ex) {
						$response = $this->showErrorMessage(I18N::translate('The file %s could not be created.', $folder_to_save . $export_file_name));
					}

				}
				//Create Gedcom 5.5.1 response
				else {
					try {
						$resource = $this->gedcom_export_service->export($this->download_tree, true, $encoding, $access_level, $line_endings);
						$root_filesystem->writeStream($folder_to_save . $export_file_name, $resource);
						fclose($resource);

						$response = $this->showSuccessMessage(I18N::translate('The family tree "%s" has been exported to: %s', $tree_name, $folder_to_save . $export_file_name));

					} catch (FilesystemException | UnableToWriteFile $ex) {
						$response = $this->showErrorMessage(I18N::translate('The file %s could not be created.', $folder_to_save . $export_file_name));
					}
				}
			}

			//If download or both
			if (($action === 'download') OR ($action === 'both')){
				
				//if download is allowed
				if(boolval($this->getPreference(self::PREF_ALLOW_DOWNLOAD, '1'))) {
					//If Gedcom 7, create Gedcom 7 response
					if (($format === 'gedcom') && ($gedcom7)) {
						$response = $this->gedcom7_export_service->downloadGedcomSevenresponse($this->download_tree, true, $encoding, $privacy, $line_endings, $file_name, $format, $gedcom_l);
					}
					//Create Gedcom 5.5.1 response
					else {
						$response = $this->gedcom_export_service->downloadResponse($this->download_tree, true, $encoding, $privacy, $line_endings, $file_name, $format);
					}
				} 
				else {
					$response = $this->showErrorMessage(I18N::translate('Download is not allowed. Please change the module settings to allow downloads.'));
				}
			}
		}
		return $response;		
    }
}