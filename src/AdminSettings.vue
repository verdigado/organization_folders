<template>
	<div>
	  <NcSettingsSection
		  name="Organization Folders"
		  :limit-width="false">
		<div v-if="!loading">
		  <Field :is="setting.sensitive ? NcPasswordField : (setting.bool ? NcCheckboxRadioSwitch : NcTextField)"
				 v-for="setting in app_settings"
				 :key="setting.id"
				 class="settings_field"
				 :model-value="settings?.[setting.id]"
				 :label="setting.name"
				 @update:modelValue="(newValue) => updateSetting(setting.id, newValue)">
				{{ setting.bool ? setting.name : "" }}
			</Field>
		</div>
	  </NcSettingsSection>
	</div>
</template>
  
<script setup>
import { ref } from "vue";
import debounceFunction from "debounce-fn";
import { translate as t, translatePlural as n } from "@nextcloud/l10n";

import NcSettingsSection from "@nextcloud/vue/components/NcSettingsSection";
import NcTextField from "@nextcloud/vue/components/NcTextField";
import NcPasswordField from "@nextcloud/vue/components/NcPasswordField";
import NcCheckboxRadioSwitch from "@nextcloud/vue/components/NcCheckboxRadioSwitch";

import { adminSettingsApi } from "./adminSettingsApi.js";

let loading = ref(true);
let settings = ref({});

const app_settings = [
	{id: "subresources_enabled", name: t("organization_folders", "Subresources Feature Enabled"), bool: true},
	{id: "hide_virtual_groups", name: t("organization_folders", "Hide Virtual Groups"), bool: true},
];

adminSettingsApi.getAllSettings().then((result) => {
	settings.value = result;
	loading.value = false;
});

const updateSetting = debounceFunction((key, value) => {
	console.log("updateSetting", key, value);
	settings.value[key] = value;
	adminSettingsApi.setSetting(key, value).then(() => {});
},
{
	wait: 1000,
});
</script>
  
<style scoped>
.settings_field {
	margin-bottom: 20px;
}
</style>
  