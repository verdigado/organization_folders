import Vue from "vue";
import { PiniaVuePlugin } from "pinia";
import { registerFileListHeaders } from '@nextcloud/files';
import { translate as t, translatePlural as n } from "@nextcloud/l10n";
import { generateFilePath } from "@nextcloud/router";
import Tooltip from "@nextcloud/vue/dist/Directives/Tooltip.js";

import { initFilesClient } from "./davClient.js";
import Header from "./header.js";
import api from "./api.js";

import '@nextcloud/dialogs/style.css';

// eslint-disable-next-line
__webpack_public_path__ = generateFilePath(appName, '', 'js/');

// Adding translations to the whole app
Vue.mixin({
  methods: {
    t,
    n,
  },
});

// Recommendation by @nextcloud/vue
Vue.prototype.OC = window.OC;
Vue.prototype.OCA = window.OCA;

Vue.directive("tooltip", Tooltip);

// Pinia
Vue.use(PiniaVuePlugin);

window.api = api;

window.addEventListener('DOMContentLoaded', () => {
    initFilesClient(OC.Files.getClient());
});

registerFileListHeaders(Header);