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
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToWriteFile;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ErrorException;
use Throwable;

use ReflectionClass;

use function substr;


class DownloadGedcomWithURL extends AbstractModule implements 
	ModuleCustomInterface, 
	ModuleConfigInterface,
	RequestHandlerInterface 
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;
 
    private GedcomExportFilterService $gedcom_export_service;

    private Tree $download_tree;

	//Custom module version
	public const CUSTOM_VERSION = '3.2.4';

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
	public const PREF_CONTROL_PANEL_SECRET_KEY = "control_panel_secret_key";
	public const PREF_USE_HASH = "use_hash";
	public const PREF_ALLOW_REMOTE_DOWNLOAD = "allow_remote_download";
	public const PREF_ALLOW_REMOTE_UPLOAD = "allow_remote_upload";
	public const PREF_ALLOW_REMOTE_SAVE = "allow_remote_save";
	public const PREF_FOLDER_TO_SAVE = "folder_to_save";
    public const PREF_DEFAULT_TREE_NAME = 'default_tree_name';
    public const PREF_DEFAULT_FiLE_NAME = 'default_file_name';
    public const PREF_DEFAULT_EXPORT_FILTER1 = 'default_export_filter1';
    public const PREF_DEFAULT_EXPORT_FILTER2 = 'default_export_filter2';
    public const PREF_DEFAULT_EXPORT_FILTER3 = 'default_export_filter3';
    public const PREF_DEFAULT_PRIVACY_LEVEL = 'default_privacy_level'; 
    public const PREF_DEFAULT_EXPORT_FORMAT = 'default_export_format';
    public const PREF_DEFAULT_ENCODING = 'default_encoding';
    public const PREF_DEFAULT_ENDING = 'default_ending';
    public const PREF_DEFAULT_ACTION = 'default_action';
    public const PREF_DEFAULT_TIME_STAMP = 'default_time_stamp';
    public const PREF_DEFAULT_GEDCOM_VERSION = 'default_gedcom_version';
    public const PREF_DEFAULT_GEDCOM_L_SELECTION = 'default_gedcom_l_selection';

	//Alert tpyes
	public const ALERT_DANGER = 'alert_danger';
	public const ALERT_SUCCESS = 'alert_success';

    //Maximum level of includes for export filters
    private const MAXIMUM_FILTER_INCLUDE_LEVELS = 10;

    //Old preferences    
	public const PREF_ALLOW_DOWNLOAD = "allow_download";


   /**
     * DownloadGedcomWithURL constructor.
     */
    public function __construct()
    {
	    $response_factory = app(ResponseFactoryInterface::class);
        $stream_factory = new Psr17Factory();

        $this->gedcom_export_service = new GedcomExportFilterService($response_factory, $stream_factory);
    }

    /**
     * Initialization.
     *
     * @return void
     */
    public function boot(): void
    {
        //Generate random key if control panel key is empty
        if ($this->getPreference(self::PREF_CONTROL_PANEL_SECRET_KEY, '') === '') {

            $this->setPreference(self::PREF_CONTROL_PANEL_SECRET_KEY, Str::random(32));
        }

        $router = Registry::routeFactory()->routeMap();            

        //Register a route for downloads
        $router
            ->get(static::class, self::ROUTE_URL, $this)
            ->allows(RequestMethodInterface::METHOD_POST);

        //Register a route for for uploads
        $router 
            ->get(RemoteImportGedcomAction::class, '/UploadGedcomWithURL', RemoteImportGedcomAction::class)
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
     * Get the active module name, e.g. the name of the currently running module
     *
     * @return string
     */
    public static function activeModuleName(): string
    {
        return '_' . basename(__DIR__) . '_';
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
        //Check update of module version
        $this->checkModuleVersionUpdate();
        
        $this->layout = 'layouts/administration';       

        //Load export filters
        $error = self::loadEportFilterClasses();
        if ($error !== '') {
            FlashMessages::addMessage($error, 'danger');
        }
        
        $tree_list = [];
        $all_trees = $this->all();

        foreach($all_trees as $tree) {
            $tree_list[$tree->name()] = $tree->name() . ' (' . $tree->title() . ')';
        }

        //Check the export filters, which are defined in the prefernces
        $this->checkFilterPreferences(self::PREF_DEFAULT_EXPORT_FILTER1);
        $this->checkFilterPreferences(self::PREF_DEFAULT_EXPORT_FILTER2);
        $this->checkFilterPreferences(self::PREF_DEFAULT_EXPORT_FILTER3);

        $data_folder = str_replace('\\', '/', Registry::filesystem()->dataName());
		$root_folder = str_replace('\\', '/', Registry::filesystem()->rootName());
		$data_folder_relative = str_replace($root_folder, '', $data_folder);

        return $this->viewResponse(
            $this->name() . '::settings',
            [
                'title'                               => $this->title(),
                'tree_list'                           => $tree_list,
                'export_filter_list'                  => $this->getExportFilterList(),
				self::PREF_SECRET_KEY                 => $this->getPreference(self::PREF_SECRET_KEY, ''),
				self::PREF_CONTROL_PANEL_SECRET_KEY   => $this->getPreference(self::PREF_CONTROL_PANEL_SECRET_KEY, ''),
				self::PREF_USE_HASH                   => boolval($this->getPreference(self::PREF_USE_HASH, '1')),
				self::PREF_ALLOW_REMOTE_DOWNLOAD      => boolval($this->getPreference(self::PREF_ALLOW_REMOTE_DOWNLOAD, '0')),
				self::PREF_ALLOW_REMOTE_UPLOAD        => boolval($this->getPreference(self::PREF_ALLOW_REMOTE_UPLOAD, '0')),
				self::PREF_ALLOW_REMOTE_SAVE          => boolval($this->getPreference(self::PREF_ALLOW_REMOTE_SAVE, '0')),
				self::PREF_FOLDER_TO_SAVE             => $this->getPreference(self::PREF_FOLDER_TO_SAVE, $data_folder_relative),
                self::PREF_DEFAULT_TREE_NAME          => $this->getPreference(self::PREF_DEFAULT_TREE_NAME, array_key_first($tree_list)),
                self::PREF_DEFAULT_FiLE_NAME          => $this->getPreference(self::PREF_DEFAULT_FiLE_NAME, array_key_first($tree_list)),
                self::PREF_DEFAULT_EXPORT_FILTER1     => $this->getPreference(self::PREF_DEFAULT_EXPORT_FILTER1, ''),
                self::PREF_DEFAULT_EXPORT_FILTER2     => $this->getPreference(self::PREF_DEFAULT_EXPORT_FILTER2, ''),
                self::PREF_DEFAULT_EXPORT_FILTER3     => $this->getPreference(self::PREF_DEFAULT_EXPORT_FILTER3, ''),
                self::PREF_DEFAULT_PRIVACY_LEVEL      => $this->getPreference(self::PREF_DEFAULT_PRIVACY_LEVEL, 'none'),
                self::PREF_DEFAULT_EXPORT_FORMAT      => $this->getPreference(self::PREF_DEFAULT_EXPORT_FORMAT, 'gedcom'),
                self::PREF_DEFAULT_ENCODING           => $this->getPreference(self::PREF_DEFAULT_ENCODING, UTF8::NAME),
                self::PREF_DEFAULT_ENDING             => $this->getPreference(self::PREF_DEFAULT_ENDING, 'CRLF'),
                self::PREF_DEFAULT_ACTION             => $this->getPreference(self::PREF_DEFAULT_ACTION, 'download'),
                self::PREF_DEFAULT_TIME_STAMP         => $this->getPreference(self::PREF_DEFAULT_TIME_STAMP, 'none'),
                self::PREF_DEFAULT_GEDCOM_VERSION     => $this->getPreference(self::PREF_DEFAULT_GEDCOM_VERSION, '0'),
                self::PREF_DEFAULT_GEDCOM_L_SELECTION => $this->getPreference(self::PREF_DEFAULT_GEDCOM_L_SELECTION, '0'),
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
        $save                       = Validator::parsedBody($request)->string('save', '');
        $use_hash                   = Validator::parsedBody($request)->boolean(self::PREF_USE_HASH, false);
        $allow_remote_download      = Validator::parsedBody($request)->boolean(self::PREF_ALLOW_REMOTE_DOWNLOAD, false);
        $allow_remote_upload        = Validator::parsedBody($request)->boolean(self::PREF_ALLOW_REMOTE_UPLOAD, false);
        $allow_remote_save          = Validator::parsedBody($request)->boolean(self::PREF_ALLOW_REMOTE_SAVE, false);
        $new_secret_key             = Validator::parsedBody($request)->string('new_secret_key', '');
        $folder_to_save             = Validator::parsedBody($request)->string(self::PREF_FOLDER_TO_SAVE, Site::getPreference('INDEX_DIRECTORY'));
        $default_tree_name          = Validator::parsedBody($request)->string(self::PREF_DEFAULT_TREE_NAME, '');
        $default_file_name          = Validator::parsedBody($request)->string(self::PREF_DEFAULT_FiLE_NAME, 'export');
        $default_export_filter1     = Validator::parsedBody($request)->string(self::PREF_DEFAULT_EXPORT_FILTER1, '');
        $default_export_filter2     = Validator::parsedBody($request)->string(self::PREF_DEFAULT_EXPORT_FILTER2, '');
        $default_export_filter3     = Validator::parsedBody($request)->string(self::PREF_DEFAULT_EXPORT_FILTER3, '');
        $default_privacy_level      = Validator::parsedBody($request)->string(self::PREF_DEFAULT_PRIVACY_LEVEL, 'none');
        $default_export_format      = Validator::parsedBody($request)->string(self::PREF_DEFAULT_EXPORT_FORMAT, 'gedcom');
        $default_encoding           = Validator::parsedBody($request)->string(self::PREF_DEFAULT_ENCODING, UTF8::NAME);
        $default_ending             = Validator::parsedBody($request)->string(self::PREF_DEFAULT_ENDING, 'CRLF');
        $default_action             = Validator::parsedBody($request)->string(self::PREF_DEFAULT_ACTION, 'download');
        $default_time_stamp         = Validator::parsedBody($request)->string(self::PREF_DEFAULT_TIME_STAMP, 'none');
        $default_gedcom_version     = Validator::parsedBody($request)->string(self::PREF_DEFAULT_GEDCOM_VERSION, '0');
        $default_gedcom_l_selection = Validator::parsedBody($request)->string(self::PREF_DEFAULT_GEDCOM_L_SELECTION, '0');
        
        //Save the received settings to the user preferences
        if ($save === '1') {

            $new_key_error = false;

            //If no new secret key is provided
			if($new_secret_key === '') {
				//If use hash changed from true to false, reset key (hash cannot be used any more)
				if(boolval($this->getPreference(self::PREF_USE_HASH, '0')) && !$use_hash) {
					$this->setPreference(self::PREF_SECRET_KEY, '');
				}
				//If use hash changed from false to true, take old key (for planned encryption) and save as hash
				elseif(!boolval($this->getPreference(self::PREF_USE_HASH, '0')) && $use_hash) {
					$new_secret_key = $this->getPreference(self::PREF_SECRET_KEY, '');
                    $hash_value = password_hash($new_secret_key, PASSWORD_BCRYPT);
                    $this->setPreference(self::PREF_SECRET_KEY, $hash_value);
				}
                //If no new secret key and no changes in hashing, do nothing
			}
			//If new secret key is too short
			elseif(strlen($new_secret_key)<8) {
				$message = I18N::translate('The provided secret key is too short. Please provide a minimum length of 8 characters.');
				FlashMessages::addMessage($message, 'danger');
                $new_key_error = true;				
			}
			//If new secret key does not escape correctly
			elseif($new_secret_key !== e($new_secret_key)) {
				$message = I18N::translate('The provided secret key contains characters, which are not accepted. Please provide a different key.');
				FlashMessages::addMessage($message, 'danger');				
                $new_key_error = true;		
            }
			//If new secret key shall be stored with a hash, create and save hash
			elseif($use_hash) {
				$hash_value = password_hash($new_secret_key, PASSWORD_BCRYPT);
				$this->setPreference(self::PREF_SECRET_KEY, $hash_value);
			}
            //Otherwise, simply store the new secret key
			else {
				$this->setPreference(self::PREF_SECRET_KEY, $new_secret_key);
			}

			//Check and set folder to save
			if (substr_compare($folder_to_save, '/', -1, 1) !== 0) {
				$folder_to_save .= '/';
			}
            
			if (substr_compare($folder_to_save, '/', 0, 1) === 0) {
				$folder_to_save = substr($folder_to_save, 1,null);
			}

            if ($folder_to_save === '') {
                $folder_to_save = '/';
            }

			if (is_dir($folder_to_save)) {
				$this->setPreference(self::PREF_FOLDER_TO_SAVE, $folder_to_save);
			} else {
				FlashMessages::addMessage(I18N::translate('The folder settings could not be saved, because the folder “%s” does not exist.', e($folder_to_save)), 'danger');
			}

            //Save settings to preferences
            if(!$new_key_error) {
                $this->setPreference(self::PREF_USE_HASH, $use_hash ? '1' : '0');
            }

            //Save settingss
			$this->setPreference(self::PREF_ALLOW_REMOTE_DOWNLOAD, $allow_remote_download ? '1' : '0');
			$this->setPreference(self::PREF_ALLOW_REMOTE_UPLOAD, $allow_remote_upload ? '1' : '0');
			$this->setPreference(self::PREF_ALLOW_REMOTE_SAVE, $allow_remote_save ? '1' : '0');

            //Save default settings to preferences
            $this->setPreference(self::PREF_DEFAULT_TREE_NAME, $default_tree_name);
            $this->setPreference(self::PREF_DEFAULT_FiLE_NAME, $default_file_name);
            $this->setPreference(self::PREF_DEFAULT_EXPORT_FILTER1, $default_export_filter1);
            $this->setPreference(self::PREF_DEFAULT_EXPORT_FILTER2, $default_export_filter2);
            $this->setPreference(self::PREF_DEFAULT_EXPORT_FILTER3, $default_export_filter3);
            $this->setPreference(self::PREF_DEFAULT_PRIVACY_LEVEL, $default_privacy_level);
            $this->setPreference(self::PREF_DEFAULT_EXPORT_FORMAT, $default_export_format);
            $this->setPreference(self::PREF_DEFAULT_ENCODING, $default_encoding);
            $this->setPreference(self::PREF_DEFAULT_ENDING, $default_ending);
            $this->setPreference(self::PREF_DEFAULT_ACTION, $default_action);
            $this->setPreference(self::PREF_DEFAULT_TIME_STAMP, $default_time_stamp);
            $this->setPreference(self::PREF_DEFAULT_GEDCOM_VERSION, $default_gedcom_version);
            $this->setPreference(self::PREF_DEFAULT_GEDCOM_L_SELECTION, $default_gedcom_l_selection);

            //Finally, show a success message
			$message = I18N::translate('The preferences for the module "%s" were updated.', $this->title());
			FlashMessages::addMessage($message, 'success');	
		}

        return redirect($this->getConfigLink());
    }

    /**
     * Check if module version is new and start update activities if needed
     *
     * @return void
     */
    public function checkModuleVersionUpdate(): void
    {
        $sucessful_update = false;

        //If secret key is already stored and secret key hashing preference is not available (i.e. before module version v3.0.1) 
        if($this->getPreference(self::PREF_SECRET_KEY, '') !== '' && $this->getPreference(self::PREF_USE_HASH, '') === '') {

			//Set secret key hashing to false
			$this->setPreference(self::PREF_USE_HASH, '0');

            $sucessful_update = true;
		}

        //Update remote preferences
        if ($this->getPreference(self::PREF_ALLOW_DOWNLOAD, '') !== '') {

            //Migrate old preference value to new preferences
            $this->setPreference(self::PREF_ALLOW_REMOTE_DOWNLOAD, self::PREF_ALLOW_DOWNLOAD);
            $this->setPreference(self::PREF_ALLOW_REMOTE_SAVE, self::PREF_ALLOW_DOWNLOAD);

            //Delete old preference value, i.e. set to ''
            $this->setPreference(self::PREF_ALLOW_DOWNLOAD, '');      
            $sucessful_update = true;      
        }

        //Update custom module version if changed
        if($this->getPreference(self::PREF_MODULE_VERSION, '') !== self::CUSTOM_VERSION) {
            $this->setPreference(self::PREF_MODULE_VERSION, self::CUSTOM_VERSION);
        }

        if ($sucessful_update) {
            //Show flash message for update of preferences
            $message = I18N::translate('The preferences for the custom module "%s" were sucessfully updated to the new module version %s.', $this->title(), self::CUSTOM_VERSION);
            FlashMessages::addMessage($message, 'success');	
        }        
    }

    /**
     * Check if an export filter is available. If not, reset export filter to none
     *
     * @param string          $preference_name     The preference name of an export filter
     * 
     * @return string                              The class name of the export filter
     */
    private function checkFilterPreferences(string $preference_name): string {

        //Get a list with the class names of all available export filters
        $export_filter_list = $this->getExportFilterList();

        //Filter name from preferences
        $export_filter_class_name = $this->getPreference($preference_name);

        //If currently selected export filter is not in the available filter list, reset export filter to none
        if (!array_key_exists($export_filter_class_name, $export_filter_list)) {

            //Reset preference
            $this->setPreference($preference_name, '');

            //Create flash message
            $message = I18N::translate('The preferences for the default export filter were reset to "none", because the selected export filter %s could not be found', $export_filter_class_name);
            FlashMessages::addMessage($message, 'danger');

            $export_filter_class_name = '';
        }
 
        //Validate the export filter
        if ($export_filter_class_name !== '' && ($error = $this->validateExportFilter($export_filter_class_name)) !== '') {
    
            FlashMessages::addMessage($error, 'danger');
        }
        
        return $export_filter_class_name;
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
     public function showErrorMessage(string $text): ResponseInterface
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
	public function showSuccessMessage(string $text): ResponseInterface
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
     * Load classes for export filters
     *
     * @return string error message
     */ 

     public static function loadEportFilterClasses(): string {

        $filter_files = scandir(dirname(__FILE__) . "/resources/filter/");

        $onError = function ($level, $message, $file, $line) {
            throw new ErrorException($message, 0, $level, $file, $line);
        };

        foreach ($filter_files as $file) {
            if (substr_compare($file, '.php', -4, 4) === 0) {

                try {
                    set_error_handler($onError);
                    require __DIR__ . '/resources/filter/' . $file;
                }
                catch (Throwable $th) {
                    return I18N::translate('A compilation error was detected in the following export filter') . ': ' . 
                    __DIR__ . '/resources/filter/' . $file . ', ' . I18N::translate('line') . ': ' . $th-> getLine() . ', ' . I18N::translate('error message') . ': ' . $th->getMessage();
                }
                finally {
                    restore_error_handler();
                }    
            }
        };

        return '';
    }

	 /**
     * Get all available export filters
     *
     * @return array<string>  An array with the class names all available export filters
     */ 

     private function getExportFilterList(): array {

        $export_filter_list =[
            ''             => I18N::translate('None'),
        ];

        foreach (get_declared_classes() as $className) {

            $name_space = str_replace('\\\\', '\\',__NAMESPACE__ ) .'\\';

            if (strpos($className, $name_space) !==  false) {

                if (in_array($name_space . 'ExportFilterInterface', class_implements($className))) {

                    $className = str_replace($name_space, '', $className);

                    if ($className !== 'AbstractExportFilter') $export_filter_list[$className] = $className;
                }
            }
        }

        return $export_filter_list;
    }

	/**
     * Validate export filter
     *
     * @return string error message
     */ 

    private function validateExportFilter($export_filter_name): string {

        //Check if export filter class is valid
        $export_filter_class_name = __NAMESPACE__ . '\\' . $export_filter_name;

        if (!class_exists($export_filter_class_name) OR !(new $export_filter_class_name() instanceof ExportFilterInterface)) {

            return I18N::translate('The export filter was not found') . ': ' . $export_filter_name;
        }

        $export_filter_instance = new $export_filter_class_name();

        //Validate the content of the export filter
        $error = $export_filter_instance->validate();

        if ($error !== '') {
            return $error;
        }

        return '';
    }

    /**
     * Check whether further filters are included in a list of export filters and add to export filter list accordingly
     *
     * @param array $export_filter_set   A set of (already inlcuded) export filters
     * @param array $additional_filters  A set of export filters to be checked and included
     * @param array $include_structure   A hierarchical list of included export filters to check loops etc.
     * 
     * @return array 
     */ 

    private function addIncludedExportFilters(array $export_filter_set, array $additional_filters, array $include_structure): array {

        while (sizeof($additional_filters) > 0) {

            //Get first item of export filter set and remove it from additional filter list
            $export_filter = array_shift($additional_filters);

            //Add export filter to include structure
            $include_structure[] = $export_filter;

            //Error if size of include structure exceeds maximum level
            if (sizeof($include_structure) > self::MAXIMUM_FILTER_INCLUDE_LEVELS) {

                $error = I18N::translate('The include hierarchy for export filters exceeds the maximum level of %s includes.', (string) self::MAXIMUM_FILTER_INCLUDE_LEVELS);

                if (in_array($export_filter, $include_structure)) {

                    $error .= ' ' . I18N::translate('The following export filter might cause a loop in the include structure, because it was detected more than once in the include hierarchy') . ': ' . (new ReflectionClass($export_filter))->getShortName();
                }
                else {
                    $error .= ' ' . I18N::translate('Please check the include structure of the selected export filters.');
                }

                throw new DownloadGedcomWithUrlException($error);
            }
            
            if ($export_filter !== null) {

                //Add include filters before
                $export_filter_set = array_merge($export_filter_set, $this->addIncludedExportFilters([], $export_filter->getIncludedFiltersBefore(), $include_structure));
                //Add filter
                array_push($export_filter_set, $export_filter);
                //Add include filters after
                $export_filter_set = array_merge($export_filter_set, $this->addIncludedExportFilters([], $export_filter->getIncludedFiltersAfter(), $include_structure));
            }
        }    

        return $export_filter_set;
    }

	/**
     * Execute the request (from URL or from control panel) to download or save 
     * 
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */	
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
		$key                 = Validator::queryParams($request)->string('key', '');
		$tree_name           = Validator::queryParams($request)->string('tree', $this->getPreference(self::PREF_DEFAULT_TREE_NAME, ''));
        $file_name           = Validator::queryParams($request)->string('file',  $this->getPreference(self::PREF_DEFAULT_FiLE_NAME, ''));
        $format              = Validator::queryParams($request)->string('format',  $this->getPreference(self::PREF_DEFAULT_EXPORT_FORMAT, 'gedcom'));
        $privacy             = Validator::queryParams($request)->string('privacy',  $this->getPreference(self::PREF_DEFAULT_PRIVACY_LEVEL, 'visitor'));
        $encoding            = Validator::queryParams($request)->string('encoding',  $this->getPreference(self::PREF_DEFAULT_ENCODING, UTF8::NAME));
        $line_endings        = Validator::queryParams($request)->string('line_endings',  $this->getPreference(self::PREF_DEFAULT_ENDING, 'CRLF'));
		$action              = Validator::queryParams($request)->string('action', $this->getPreference(self::PREF_DEFAULT_ACTION, 'download'));
		$time_stamp          = Validator::queryParams($request)->string('time_stamp', $this->getPreference(self::PREF_DEFAULT_TIME_STAMP, 'none'));
		$export_filter1      = Validator::queryParams($request)->string('export_filter1', $this->getPreference(self::PREF_DEFAULT_EXPORT_FILTER1, ''));
		$export_filter2      = Validator::queryParams($request)->string('export_filter2', $this->getPreference(self::PREF_DEFAULT_EXPORT_FILTER2, ''));
		$export_filter3      = Validator::queryParams($request)->string('export_filter3', $this->getPreference(self::PREF_DEFAULT_EXPORT_FILTER3, ''));

        if ($file_name === '') {
            $file_name = $tree_name;
        }

        //A download from control panel is recognized if a certain key is received
        $called_from_control_panel = $key === $this->getPreference(self::PREF_CONTROL_PANEL_SECRET_KEY, '') . Session::getCsrfToken();

        //Check update of module version
        $this->checkModuleVersionUpdate();

        //Add namespace to export filters
        $export_filter_class_name1 = __NAMESPACE__ . '\\' . $export_filter1;
        $export_filter_class_name2 = __NAMESPACE__ . '\\' . $export_filter2;
        $export_filter_class_name3 = __NAMESPACE__ . '\\' . $export_filter3;

        //Load export filter classes
        if ($export_filter1 !== '' OR $export_filter2 !== '' OR $export_filter3 !== '') {
            $load_filter_result = self::loadEportFilterClasses();

            if ($load_filter_result !== '') {
                return $this->showErrorMessage($load_filter_result);
            }    
        }

        //If not called from control panel (i.e. called remotely via URL), evaluate key
        if (!$called_from_control_panel) {

            //Load secret key from preferences
            $secret_key = $this->getPreference(self::PREF_SECRET_KEY, '');

            //Error if key is empty
            if ($key === '') {
                return $this->showErrorMessage(I18N::translate('No key provided. For checking of the access rights, it is mandatory to provide a key as parameter in the URL.'));
            }
            //Error if secret key is empty
            elseif ($secret_key === '') {
                return $this->showErrorMessage(I18N::translate('No secret key defined. Please define secret key in the module settings: Control Panel / Modules / All Modules / ') . $this->title());
            }
            //Error if no hashing and key is not valid
            elseif (!boolval($this->getPreference(self::PREF_USE_HASH, '0')) && ($key !== $secret_key)) {
                return $this->showErrorMessage(I18N::translate('Key not accepted. Access denied.'));
            }
            //Error if hashing and key does not fit to hash
            elseif (boolval($this->getPreference(self::PREF_USE_HASH, '0')) && (!password_verify($key, $secret_key))) {
                return $this->showErrorMessage(I18N::translate('Key (encrypted) not accepted. Access denied.'));
            }     
        }

        //Error if tree name is not valid
        if (!$this->isValidTree($tree_name)) {
			return $this->showErrorMessage(I18N::translate('Tree not found') . ': ' . $tree_name);
		}
        //Error if privacy level is not valid
		elseif (!in_array($privacy, ['none', 'gedadmin', 'user', 'visitor'])) {
			return $this->showErrorMessage(I18N::translate('Privacy level not accepted') . ': ' . $privacy);
        }
        //Error if export format is not valid
        elseif (!in_array($format, ['gedcom', 'zip', 'zipmedia', 'gedzip'])) {
			return $this->showErrorMessage(I18N::translate('Export format not accepted') . ': ' . $format);
        }       
        //Error if encoding is not valid
		elseif (!in_array($encoding, [UTF8::NAME, UTF16BE::NAME, ANSEL::NAME, ASCII::NAME, Windows1252::NAME])) {
			return $this->showErrorMessage(I18N::translate('Encoding not accepted') . ': ' . $encoding);
        }       
        //Error action is not valid
        elseif (!in_array($action, ['download', 'save', 'both'])) {
			return $this->showErrorMessage(I18N::translate('Action not accepted') . ': ' . $action);
        }  
		//Error if line ending is not valid
        elseif (!in_array($line_endings, ['CRLF', 'LF'])) {
			return $this->showErrorMessage(I18N::translate('Line endings not accepted') . ': ' . $line_endings);
        } 
		//Error if time_stamp is not valid
        elseif (!in_array($time_stamp, ['prefix', 'postfix', 'none'])) {
			return $this->showErrorMessage(I18N::translate('Time stamp setting not accepted') . ': ' . $time_stamp);
        } 	
		//Error if export filter 1 is not found
        elseif ($export_filter1 !== '' && (!class_exists($export_filter_class_name1) OR !(new $export_filter_class_name1() instanceof ExportFilterInterface))) {
            return $this->showErrorMessage(I18N::translate('The export filter was not found') . ': ' . $export_filter1);
        }
		//Error if export filter 2 is not found
        elseif ($export_filter2 !== '' && (!class_exists($export_filter_class_name2) OR !(new $export_filter_class_name2() instanceof ExportFilterInterface))) {
            return $this->showErrorMessage(I18N::translate('The export filter was not found') . ': ' . $export_filter2);
        }
		//Error if export filter 3 is not found
        elseif ($export_filter3 !== '' && (!class_exists($export_filter_class_name3) OR !(new $export_filter_class_name3() instanceof ExportFilterInterface))) {
            return $this->showErrorMessage(I18N::translate('The export filter was not found') . ': ' . $export_filter3);
        }
		//Error if export filter 1 validation fails
        elseif ($export_filter1 !== '' && ($error = $this->validateExportFilter($export_filter1)) !== '') {
            return $this->showErrorMessage($error);
        }
		//Error if export filter 2 validation fails
        elseif ($export_filter2 !== '' && ($error = $this->validateExportFilter($export_filter2)) !== '') {
            return $this->showErrorMessage($error);
        }
		//Error if export filter 3 validation fails
        elseif ($export_filter3 !== '' && ($error = $this->validateExportFilter($export_filter3)) !== '') {
            return $this->showErrorMessage($error);
        }

		//If no errors, execute the core activities of the module
        //Get instance of export filter 1
        if ($export_filter1 !== '') {
            $export_filter_instance1 = new $export_filter_class_name1();
        }
        else {
            $export_filter_instance1 = null;
        }

        //Get instance of export filter 2
        if ($export_filter2 !== '') {
            $export_filter_instance2 = new $export_filter_class_name2();
        }
        else {
            $export_filter_instance2 = null;
        }

        //Get instance of export filter 3
        if ($export_filter3 !== '') {
            $export_filter_instance3 = new $export_filter_class_name3();
        }
        else {
            $export_filter_instance3 = null;
        }

        //Create set of export filters
        $filters_to_add = [
            $export_filter_instance1,
            $export_filter_instance2,
            $export_filter_instance3,
        ];

        //Add export filters, which might also add further exports filters from their include lists
        try{
            $export_filter_set = $this->addIncludedExportFilters([], $filters_to_add, []);
        }
        catch (DownloadGedcomWithUrlException $ex) {

            return $this->showErrorMessage($ex->getMessage());
        }

        //Add time stamp to file name if requested
        if($time_stamp === 'prefix'){
            $file_name = date('Y-m-d_H-i-s_') . $file_name;
        } 
        elseif($time_stamp === 'postfix'){
            $file_name .= date('_Y-m-d_H-i-s');
        }

        //If saving to server is requested and allowed
        if ($action === 'save' OR $action === 'both') {

            if ($called_from_control_panel OR boolval($this->getPreference(self::PREF_ALLOW_REMOTE_SAVE, '1'))) {

                $root_filesystem = Registry::filesystem()->root();
                $export_file_name = $file_name;

                // Force a ".ged" suffix
                if (strtolower(pathinfo($export_file_name, PATHINFO_EXTENSION)) !== 'ged') {
                    $export_file_name .= '.ged';
                }

                //Get folder from settings
                $folder_to_save = $this->getPreference(self::PREF_FOLDER_TO_SAVE, '');

                //Create response
                try {
                    $resource = $this->gedcom_export_service->filteredSaveResponse($this->download_tree, true, $encoding, $privacy, $line_endings, $format, $export_filter_set);
                    $root_filesystem->writeStream($folder_to_save . $export_file_name, $resource);
                    fclose($resource);
                    $response = $this->showSuccessMessage(I18N::translate('The family tree "%s" has been exported to: %s', $tree_name, $folder_to_save . $export_file_name));
                } 
                catch (FilesystemException | UnableToWriteFile | DownloadGedcomWithUrlException $ex) {

                    if ($ex instanceof DownloadGedcomWithUrlException) {
                        return $this->showErrorMessage($ex->getMessage());
                    }
                    else {
                        return $this->showErrorMessage(I18N::translate('The file %s could not be created.', $folder_to_save . $export_file_name));
                    }
                }
            }
            else {
                return $this->showErrorMessage( I18N::translate('Remote URL requests to save GEDCOM files to the server are not allowed.') . ' ' . 
                                                I18N::translate('Please check the module settings in the control panel.'));
            }
        }

        //If download is requested and allowed
        if ($action === 'download' OR $action === 'both') {
            if ($called_from_control_panel OR boolval($this->getPreference(self::PREF_ALLOW_REMOTE_DOWNLOAD, '1'))) {
                try {
                    //Create response
                    $response = $this->gedcom_export_service->filteredDownloadResponse($this->download_tree, true, $encoding, $privacy, $line_endings, $file_name, $format, $export_filter_set);
                }
                catch (DownloadGedcomWithUrlException $ex) {
                    return $this->showErrorMessage($ex->getMessage());
                }
            }
            else {
                return $this->showErrorMessage( I18N::translate('Remote URL requests to download GEDCOM files are not allowed.') . ' ' . 
                                                I18N::translate('Please check the module settings in the control panel.'));
            }
        }

        return $response;
    }
}
