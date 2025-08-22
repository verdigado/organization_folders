<!--
  - @copyright Copyright (c) 2025 Jonathan Treffler <jonathan.treffler@verdigado.com>
  -
  - @author Jonathan Treffler <jonathan.treffler@verdigado.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<script setup>
import { computed, ref } from 'vue';

import NcActions from "@nextcloud/vue/components/NcActions";
import NcActionButton from "@nextcloud/vue/components/NcActionButton";
import NcDialog from "@nextcloud/vue/components/NcDialog";
import NcButton from "@nextcloud/vue/components/NcButton";
import NcLoadingIcon from "@nextcloud/vue/components/NcLoadingIcon";
import NcTextField from "@nextcloud/vue/components/NcTextField";

import Plus from "vue-material-design-icons/Plus.vue";
import Cancel from "vue-material-design-icons/Cancel.vue";
import Check from "vue-material-design-icons/Check.vue";

import QuotaSelector from "./QuotaSelector.vue";
import SubSection from "./SubSection.vue";
import SubSectionHeader from "./SubSectionHeader.vue";

import { validOrganizationFolderName } from "../helpers/validation.js";
import { unlimitedQuota } from "../helpers/file-size-helpers.js"

const emit = defineEmits(["add-organization-folder"]);

const dialogOpen = ref(false);
const loading = ref(false);

const currentName = ref("");
const currentQuota = ref(unlimitedQuota);

const currentNameValid = computed(() => {
	return validOrganizationFolderName(currentName.value); 
});

const dialogSubmit = () => {
	loading.value = true;
	emit("add-organization-folder", currentName.value, currentQuota.value, () => {
		dialogOpen.value = false;
		currentName.value = "";
		currentQuota.value = unlimitedQuota;
		loading.value = false;
	});
};

const dialogClose = () => {
	dialogOpen.value = false;
	currentName.value = "";
	currentQuota.value = unlimitedQuota;
};

const dialogUpdate = (open) => {
	dialogOpen.value = open;
	if(!open) {
		dialogClose();
	}
};

</script>

<template>
	<div class="ignoreForLayout">
		<NcActions type="secondary" style="height: 100%;">
			<NcActionButton	@click="dialogOpen = true">
				<template #icon>
					<Plus :size="20" />
				</template>
				{{ t('organization_folders', 'Create Organization Folder') }}
			</NcActionButton>
		</NcActions>
		<NcDialog :open="dialogOpen"
			:name="t('organization_folders', 'Create Organization Folder')"
			size="large"
			@update:open="dialogUpdate">
			<div style="display: flex; flex-direction: column; row-gap: 10px; margin: 20px;">
				<SubSection>
					<template #header>
						<SubSectionHeader :text="t('organization_folders', 'Name')" />
					</template>
					<NcTextField :value.sync="currentName"
						:error="!currentNameValid"
						:helper-text="currentNameValid ? '' : t('organization_folders', 'Invalid name')"
						:label="t('organization_folders', 'Name')"
						:label-outside="true"
						placeholder=""
						style="--color-border-maxcontrast: #949494;"
						@blur="() => currentName = currentName.trim()" />
				</SubSection>

				<SubSection>
					<template #header>
						<SubSectionHeader :text="t('organization_folders', 'Storage Quota')" />
					</template>
					<QuotaSelector v-model="currentQuota" style="width: 100%;" />
				</SubSection>
			</div>
			<template #actions>
				<NcButton @click="dialogClose">
					<template #icon>
						<Cancel :size="20" />
					</template>
					{{ t("organization_folders", "Cancel") }}
				</NcButton>
				<NcButton :disabled="!currentName || !currentNameValid || loading" @click="dialogSubmit">
					<template #icon>
						<NcLoadingIcon v-if="loading" />
						<Check v-else :size="20" />
					</template>
					{{ t("organization_folders", "Create") }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>
