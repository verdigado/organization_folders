<script setup>
import { ref } from "vue";
import { NcActions, NcActionInput } from '@nextcloud/vue';

import Folder from "vue-material-design-icons/Folder.vue";
import Plus from "vue-material-design-icons/Plus.vue";

import api from "../api.js";
import { validResourceName } from "../helpers/validation.js";

const emit = defineEmits(["create"]);

const newFolderResourceName = ref("");
</script>
<template>
    <NcActions type="secondary">
        <template #icon>
            <Plus :size="20" />
        </template>
        <NcActionInput v-model="newFolderResourceName" label="Ordner erstellen" @submit="emit('create', api.ResourceTypes.FOLDER, newFolderResourceName)">
            <template #icon>
                <Folder :size="20" />
            </template>
            Ordner Name
        </NcActionInput>
    </NcActions>
</template>