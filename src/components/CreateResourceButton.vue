<script setup>
import { ref } from "vue";
import { translate as t, translatePlural as n } from "@nextcloud/l10n";

import NcActions from "@nextcloud/vue/components/NcActions";
import NcActionInput from "@nextcloud/vue/components/NcActionInput";
import NcLoadingIcon from "@nextcloud/vue/components/NcLoadingIcon";

import Folder from "vue-material-design-icons/Folder.vue";
import Plus from "vue-material-design-icons/Plus.vue";

import api from "../api.js";
import { validResourceName } from "../helpers/validation.js";

const emit = defineEmits(["create"]);

const open = ref(false);
const loading = ref(false);

const newFolderResourceName = ref("");

const onSubmit = () => {
    loading.value = true;

	if(validResourceName(newFolderResourceName.value)) {
		emit('create', api.ResourceTypes.FOLDER, newFolderResourceName.value, (success) => {
            if(success) {
                open.value = false;
            }

            loading.value = false;
        });
	}
};
</script>

<template>
    <NcActions :open.sync="open" type="secondary">
        <template #icon>
            <Plus :size="20" />
        </template>
        <NcActionInput v-model="newFolderResourceName"
			:show-trailing-button="validResourceName(newFolderResourceName) && !loading"
            trailing-button-icon="arrowEnd"
			:label="t('organization_folders', 'Create folder')"
			@submit="onSubmit">
            <template #icon>
                <!-- TODO: It would be better to show the spinner where the trailing button is, but that is not currently possible -->
                <NcLoadingIcon v-if="loading" :size="20" />
                <Folder v-else :size="20" />
            </template>
            {{  t('organization_folders', 'Folder name') }}
        </NcActionInput>
    </NcActions>
</template>