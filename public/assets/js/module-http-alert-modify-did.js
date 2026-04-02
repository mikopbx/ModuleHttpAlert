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
var ModuleDidUrlModify = {
  $formObj: $('#module-did-url-form'),

  /**
   * Field validation rules
   * https://semantic-ui.com/behaviors/form.html
   */
  validateRules: {},

  /**
   * On page load init some Semantic UI library
   */
  initialize: function initialize() {
    ModuleDidUrlModify.initializeForm();
  },

  /**
   * We can modify some data before form send
   * @param settings
   * @returns {*}
   */
  cbBeforeSendForm: function cbBeforeSendForm(settings) {
    var result = settings;
    result.data = ModuleDidUrlModify.$formObj.form('get values');
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
    Form.$formObj = ModuleDidUrlModify.$formObj;
    Form.url = "".concat(globalRootUrl, "module-http-alert/module-http-alert/saveDid");
    Form.validateRules = ModuleDidUrlModify.validateRules;
    Form.cbBeforeSendForm = ModuleDidUrlModify.cbBeforeSendForm;
    Form.cbAfterSendForm = ModuleDidUrlModify.cbAfterSendForm;
    Form.initialize();
  }
};
$(document).ready(function () {
  ModuleDidUrlModify.initialize();
});
//# sourceMappingURL=module-http-alert-modify-did.js.map