<script setup>
import { ref } from "vue";
import { translate as t, translatePlural as n } from "@nextcloud/l10n";

import NcActions from "@nextcloud/vue/components/NcActions";
import NcActionInput from "@nextcloud/vue/components/NcActionInput";

import Folder from "vue-material-design-icons/Folder.vue";
import Plus from "vue-material-design-icons/Plus.vue";

import api from "../api.js";
import { validResourceName } from "../helpers/validation.js";

const emit = defineEmits(["create"]);

const open = ref(false);

const newFolderResourceName = ref("");

const onSubmit = () => {
	if(validResourceName(newFolderResourceName.value)) {
		emit('create', api.ResourceTypes.FOLDER, newFolderResourceName.value);
		open.value = false;
	}
};
</script>

<template>
    <NcActions :open.sync="open" type="secondary">
        <template #icon>
            <Plus :size="20" />
        </template>
        <NcActionInput v-model="newFolderResourceName"
			:show-trailing-button="validResourceName(newFolderResourceName)"
			:label="t('organization_folders', 'Create folder')"
			@submit="onSubmit">
            <template #icon>
                <Folder :size="20" />
            </template>
            {{  t('organization_folders', 'Folder name') }}
        </NcActionInput>
    </NcActions>
</template>