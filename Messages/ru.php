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

return [
	'repModuleHttpAlert'         => 'Web hooks - %repesent%',
	'mo_ModuleModuleHttpAlert'   => 'Web hooks',
    'BreadcrumbModuleHttpAlert'  => 'Web hooks',
    'SubHeaderModuleHttpAlert'   => 'Уведомление по HTTP о звонках',

    'module_httpAlert_AddDidRule'    => 'Добавить новое правило DID',
    'module_httpAlert_AddNewRecord'  => 'Добавить',

    'module_httpAlert_incomingOnly'  => 'Оповещать только о входящих',
    'module_httpAlert_urlDial'       => 'Параметры URL. Вызов поступил на сотрудника (action=call)',
    'module_httpAlert_urlDialEnd'    => 'URL для оповещения об окончании звонка (action=end-dial)',
    'module_httpAlert_urlCreateChan' => 'URL для оповещения о создании нового канала (action=create-chan)',
    'module_httpAlert_urlAnswer'     => 'Параметры URL. Сотрудник ответил на вызов (action=answer)',
    'module_httpAlert_urlHangup'     => 'URL для оповещения о завершении канала (action=hangup)',
    'module_httpAlert_urlCompleteCdr'       => 'Параметры URL. Окончание разговора пользователя (action=end-call)',
    'module_httpAlert_urlCompleteCdrGlobal' => 'Параметры URL. Окончание звонка (action=end-call-global)',
    'module_httpAlert_urlStartCall'         => 'Параметры URL. Вызов поступил на АТС.',

    'module_httpAlert_did'  => 'Перечислите через пробел DID или оставьте пустым',
    'module_httpAlert_did_shot'  => 'DID номер',
    'module_httpAlert_url'  => 'Базовый URL для DID',
    'module_httpAlert_uWwwasic'  => 'Имя пользователь для HTTP Basic',
    'module_httpAlert_pWwwBasic' => 'Пароль для HTTP Basic',

    'module_template_AdditionalTabContent'  => 'Модуль может содержать несколько разных страниц, при желании их можно добавить в меню',
    'module_template_AdditionalSubMenuItem' => 'Пример подменю с отдельной страницей',
    'module_httpAlert_ChangeRecord'  => 'Изменить параметры модуля',
    'BreadcrumbAdditionalPage'      => 'Пример отдельного контроллера для модуля'
];