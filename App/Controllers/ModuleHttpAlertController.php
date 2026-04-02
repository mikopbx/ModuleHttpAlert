<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2023 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace Modules\ModuleHttpAlert\App\Controllers;
use MikoPBX\AdminCabinet\Controllers\BaseController;
use MikoPBX\AdminCabinet\Providers\AssetProvider;
use MikoPBX\Common\Models\Providers;
use MikoPBX\Modules\PbxExtensionUtils;
use Modules\ModuleHttpAlert\App\Forms\ModuleDidUrlForm;
use Modules\ModuleHttpAlert\App\Forms\ModuleHttpAlertForm;
use Modules\ModuleHttpAlert\Models\ModuleDidUrl;
use Modules\ModuleHttpAlert\Models\ModuleHttpAlert;

class ModuleHttpAlertController extends BaseController
{
    private string $moduleUniqueID = 'ModuleHttpAlert';
    private string $moduleDir;

    /**
     * Basic initial class
     */
    public function initialize(): void
    {
        $this->moduleDir = PbxExtensionUtils::getModuleDir($this->moduleUniqueID);
        $this->view->logoImagePath = $this->url->get().'assets/img/cache/'.$this->moduleUniqueID.'/logo.svg';
        $this->view->submitMode = null;
        parent::initialize();
    }

    /**
     * Renders the index page for the module.
     *
     * @return void
     */
    public function indexAction(): void
    {
        $headerCollectionCSS = $this->assets->collection(AssetProvider::HEADER_CSS);
        $headerCollectionCSS->addCss('css/cache/'.$this->moduleUniqueID.'/module-http-alert-index.css', true);

        // Add JavaScript files to the footer collection
        $footerCollectionJS = $this->assets->collection(AssetProvider::FOOTER_JS);
        $footerCollectionJS->addJs('js/cache/'.$this->moduleUniqueID.'/module-http-alert-index.js', true);

        $this->view->pick('Modules/'.$this->moduleUniqueID.'/ModuleHttpAlert/index');

        $this->view->didUrl = ModuleDidUrl::find();
    }

    /**
     * @param string $id
     * @return void
     */
    public function modifyDidAction(string $id = ''): void
    {
        // Add JavaScript files to the footer collection
        $footerCollectionJS = $this->assets->collection(AssetProvider::FOOTER_JS);
        $footerCollectionJS
            ->addJs('js/pbx/main/form.js', true)
            ->addJs('js/cache/'.$this->moduleUniqueID.'/module-http-alert-modify-did.js', true);

        if(!is_numeric($id)){
            $id = '';
        }
        // Retrieve or create new module settings
        $settings = ModuleDidUrl::findFirstById($id);
        if ($settings === null) {
            $settings = new ModuleDidUrl();
        }
        // Create options array for form
        $options = [];
        $this->view->form = new ModuleDidUrlForm($settings, $options);
        $this->view->pick('Modules/'.$this->moduleUniqueID.'/ModuleHttpAlert/modifyDid');
    }

    /**
     * Saves the form data to the database.
     * @param string $id
     * @return void
     */
    public function saveDidAction(string $id='') :void
    {
        if (!$this->request->isPost()) {
            return;
        }
        $data = $this->request->getPost();
        $record = ModuleDidUrl::findFirstById($data['id']);
        if ($record === null) {
            $record = new ModuleDidUrl();
        }
        foreach ($record as $key => $value) {
            if($key !== "id"){
                if (array_key_exists($key, $data)) {
                    $record->$key = $data[$key];
                } else {
                    $record->$key = '';
                }
            }
        }
        $this->saveEntity($record, "module-http-alert/module-http-alert/index");
    }

    /**
     * Saves the form data to the database.
     * @param string $id
     * @return void
     */
    public function deleteDidAction(string $id='') :void
    {
        $reloadPath = "module-http-alert/module-http-alert/index";
        $record = ModuleDidUrl::findFirstById($id);
        if ($record ) {
            $this->deleteEntity($record, $reloadPath);
        }else{
            $this->forward($reloadPath);
        }
    }

    /**
     * Renders the modify page for the module.
     *
     * @return void
     */
    public function modifyAction(): void
    {
        // Add JavaScript files to the footer collection
        $footerCollectionJS = $this->assets->collection(AssetProvider::FOOTER_JS);
        $footerCollectionJS
            ->addJs('js/pbx/main/form.js', true)
            ->addJs('js/cache/'.$this->moduleUniqueID.'/module-http-alert-modify.js', true);

        // Retrieve or create new module settings
        $settings = ModuleHttpAlert::findFirst();
        if ($settings === null) {
            $settings = new ModuleHttpAlert();
        }

        // Create options array for form
        $options = [];

        // Retrieve providers list for form
        $providers = Providers::find();
        $providersList = [];
        foreach ($providers as $provider){
            $providersList[ $provider->uniqid ] = $provider->getRepresent();
        }
        $options['providers']=$providersList;

        $this->view->form = new ModuleHttpAlertForm($settings, $options);

        $this->view->pick('Modules/'.$this->moduleUniqueID.'/ModuleHttpAlert/modify');
    }


    /**
     * Saves the form data to the database.
     *
     * @return void
     */
    public function saveAction() :void
    {
        if (!$this->request->isPost()) {
            return;
        }
        $data = $this->request->getPost();
        $record = ModuleHttpAlert::findFirstById($data['id']);
        if ($record === null) {
            $record = new ModuleHttpAlert();
        }
        foreach ($record as $key => $value) {
            switch ($key) {
                case 'id':
                    break;
                case 'incomingOnly':
                    if (array_key_exists($key, $data)) {
                        $record->$key = ($data[$key] === 'on') ? '1' : '0';
                    } else {
                        $record->$key = '0';
                    }
                    break;
                default:
                    if (array_key_exists($key, $data)) {
                        $record->$key = $data[$key];
                    } else {
                        $record->$key = '';
                    }
            }
        }

      $this->saveEntity($record);
    }

    /**
     * Deletes a record from db.
     *
     * @param string $recordId
     * @return void
     */
    public function deleteAction(string $recordId): void
    {
        $record = ModuleHttpAlert::findFirstById($recordId);
        if ($record !== null) {
            $this->deleteEntity($record,'module-http-alert/module-http-alert/index');
        }
    }

}