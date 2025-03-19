<template>
	<NcSelect v-model="selectedGroup"
		@update:modelValue="onSelection"
		:options="options"
		:aria-label-combobox="'Group Select'"
		label="displayName"
		@search="onSearch" />
</template>


<script setup>
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js';

import api from "../../api.js";

import { ref } from 'vue';

const props = defineProps({
	findGroupMemberOptions: {
		type: Function,
		required: false,
		default: async () => [],
	},
});

const emit = defineEmits(["selected"]);

const selectedGroup = ref(null);

const options = ref([]);

const onSearch = (search) => {
	props.findGroupMemberOptions(search).then((newOptions) => {
		options.value = newOptions;
	});
};

const onSelection = (newSelectedGroup) => {
	if(newSelectedGroup) {
		emit("selected", api.PrincipalTypes.GROUP, newSelectedGroup.id);
	} else {
		emit("selected", null, null);
	}
    
};
</script>
