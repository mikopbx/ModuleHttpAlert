"use strict";

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

/* global globalRootUrl, globalTranslate, Form, Config */
var ModuleHttpAlertModify = {
  $formObj: $('#module-http-alert-form'),
  $checkBoxes: $('#module-http-alert-form .ui.checkbox'),
  $dropDowns: $('#module-http-alert-form .ui.dropdown'),

  /**
   * Field validation rules
   * https://semantic-ui.com/behaviors/form.html
   */
  validateRules: {
    textField: {
      identifier: 'text_field',
      rules: [{
        type: 'empty',
        prompt: globalTranslate.module_template_ValidateValueIsEmpty
      }]
    },
    areaField: {
      identifier: 'text_area_field',
      rules: [{
        type: 'empty',
        prompt: globalTranslate.module_template_ValidateValueIsEmpty
      }]
    },
    passwordField: {
      identifier: 'password_field',
      rules: [{
        type: 'empty',
        prompt: globalTranslate.module_template_ValidateValueIsEmpty
      }]
    }
  },

  /**
   * On page load init some Semantic UI library
   */
  initialize: function initialize() {
    ModuleHttpAlertModify.$checkBoxes.checkbox();
    ModuleHttpAlertModify.$dropDowns.dropdown();
    ModuleHttpAlertModify.initializeForm();
  },

  /**
   * We can modify some data before form send
   * @param settings
   * @returns {*}
   */
  cbBeforeSendForm: function cbBeforeSendForm(settings) {
    var result = settings;
    result.data = ModuleHttpAlertModify.$formObj.form('get values');
    return result;
  },

  /**
   * Some actions after forms send
   */
  cbAfterSendForm: function cbAfterSendForm() {},

  /**
   * Initialize form parameters
   */
  initializeForm: function initializeForm() {
    Form.$formObj = ModuleHttpAlertModify.$formObj;
    Form.url = "".concat(globalRootUrl, "module-http-alert/module-http-alert/save");
    Form.validateRules = ModuleHttpAlertModify.validateRules;
    Form.cbBeforeSendForm = ModuleHttpAlertModify.cbBeforeSendForm;
    Form.cbAfterSendForm = ModuleHttpAlertModify.cbAfterSendForm;
    Form.initialize();
  }
};
$(document).ready(function () {
  ModuleHttpAlertModify.initialize();
});
//# sourceMappingURL=module-http-alert-modify.js.map