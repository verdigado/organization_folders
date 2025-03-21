<script setup>
import { computed, ref } from "vue";

import NcListItem from "@nextcloud/vue/components/NcListItem";
import NcTextField from "@nextcloud/vue/components/NcTextField";
import NcEmptyContent from "@nextcloud/vue/components/NcEmptyContent";

import Magnify from "vue-material-design-icons/Magnify.vue";
import CheckboxBlankCircle from "vue-material-design-icons/CheckboxBlankCircle.vue";
import Folder from "vue-material-design-icons/Folder.vue";
import FolderOff from "vue-material-design-icons/FolderOff.vue";

import api from "../api.js";

const emit = defineEmits(["click:resource"]);

const props = defineProps({
	resources: {
		type: Array,
		required: true,
	},
    enableSearch: {
        type: Boolean,
        default: false,
    }
});

const search = ref("");

const filteredResources = computed(() => props.resources.filter((g) => g.name.toLowerCase().includes(search.value.toLowerCase())))
</script>

<template>
	<div>
        <NcTextField v-if="props.enableSearch"
            :value.sync="search"
			label="Suche..."
			class="search-input"
			trailing-button-icon="close"
			:show-trailing-button="search !== ''"
			@trailing-button-click="search = ''">
			<Magnify :size="16" />
		</NcTextField>
		<NcEmptyContent v-if="resources.length === 0" name="Keine Unter-Resourcen vorhanden">
            <template #icon>
                <FolderOff />
            </template>
        </NcEmptyContent>
		<ul v-else>
			<NcListItem v-for="resource in filteredResources"
				:key="resource.id"
				class="resource-list material_you"
				:name="resource.name"
				:linkAriaLabel="resource.name"
				:force-display-actions="true"
				@click="() => emit('click:resource', resource)">
				<template #icon>
					<Folder v-if="resource.type === api.ResourceTypes.FOLDER" :size="44" />
				</template>
				<template #indicator>
					<CheckboxBlankCircle v-tooltip="resource.active ? 'aktiviert' : 'nicht aktiviert'" :size="16" :fill-color="resource.active ? 'var(--color-primary)' : '#333'" />
				</template>
				<template #actions>
					actions
				</template>
			</NcListItem>
		</ul>
	</div>
</template>

<style scoped>
.search-input {
	width: 100%;
	margin-bottom: 10px;
}

/* center the indicator icon for folder active state " */
.resource-list {
	position: relative;
}

/deep/ .resource-list .line-two__additional_elements {
	position: absolute;
	top: calc(50% - 8px);
	right: 25px;
	margin: 0;
	height: 20px;
}
</style>
