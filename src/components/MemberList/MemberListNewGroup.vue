<template>
	<div class="input-row">
		<NcSelect v-model="selectedGroup"
			:options="options"
	  		:aria-label-combobox="'Group Select'"
			label="displayName"
			@search="onSearch" />
		
		<NcButton :disabled="!selectedGroup"
			@click="onSave">
			<template #icon>
				<Plus />
			</template>
			Hinzufügen
		</NcButton>
	</div>
</template>


<script setup>
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js';
import NcButton from "@nextcloud/vue/dist/Components/NcButton.js";
import Plus from "vue-material-design-icons/Plus.vue";

import { ref } from 'vue';

const props = defineProps({
	findGroupMemberOptions: {
		type: Function,
		required: false,
		default: async () => [],
	},
});

const emit = defineEmits(["add-member"]);

const selectedGroup = ref(null);

const options = ref([]);

const onSearch = (search) => {
	props.findGroupMemberOptions(search).then((newOptions) => {
		options.value = newOptions;
	});
};

const onSave = () => {
	emit("add-member", selectedGroup.value.id);
};

</script>
<style scoped>
.input-row {
	display: flex;
	justify-content: flex-start;
}
</style>
