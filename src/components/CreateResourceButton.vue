<script setup>
import { ref, onBeforeMount, set } from "vue";
import { translate as t, translatePlural as n } from "@nextcloud/l10n";

import NcActions from "@nextcloud/vue/components/NcActions";
import NcActionInput from "@nextcloud/vue/components/NcActionInput";
import NcLoadingIcon from "@nextcloud/vue/components/NcLoadingIcon";

import Folder from "vue-material-design-icons/Folder.vue";
import Calendar from "vue-material-design-icons/Calendar.vue";
import Plus from "vue-material-design-icons/Plus.vue";

import api from "../api.js";
import { validResourceName } from "../helpers/validation.js";

const props = defineProps({
	types: {
		type: Array,
		required: true,
	},
});

const emit = defineEmits(["create"]);

const open = ref(false);

const loading = ref(false);
const newResourceName = ref({});

onBeforeMount(() => {
    for(let type of props.types) {
        set(newResourceName, type, "");
    }
});

const labelByType = {
    folder: t('organization_folders', 'Create folder'),
    calendar: t('organization_folders', 'Create calendar'),
};

const placeholderByType = {
    folder: t('organization_folders', 'Folder name'),
    calendar: t('organization_folders', 'Calendar name'),
};

const iconByType = {
    folder: Folder,
    calendar: Calendar,
}

const onSubmit = (type) => {
    if(!loading.value) {
        loading.value = type;

        if(validResourceName(newResourceName.value[type])) {
            emit('create', type, newResourceName.value[type], (success) => {
                if(success) {
                    open.value = false;
                    newResourceName.value[type] = "";
                }
                loading.value = false;
            });
        }
    }
};
</script>

<template>
    <NcActions :open.sync="open" type="secondary">
        <template #icon>
            <Plus :size="20" />
        </template>
        <NcActionInput v-for="type in types"
            :key="type"
            v-model="newResourceName[type]"
			:show-trailing-button="validResourceName(newResourceName[type]) && !loading"
            trailing-button-icon="arrowEnd"
			:label="labelByType[type]"
			@submit="onSubmit(type)">
            <template #icon>
                <!-- TODO: It would be better to show the spinner where the trailing button is, but that is not currently possible -->
                <NcLoadingIcon v-if="loading === type" :size="20" />
                <component v-else :is="iconByType[type]" :size="20" />
            </template>
            {{ placeholderByType[type] }}
        </NcActionInput>
    </NcActions>
</template>