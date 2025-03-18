<template>
	<div class="input-row">
		<NcSelect v-model="selectedUser"
			:options="options"
	  		:aria-label-combobox="'User Select'"
			:user-select="true"
			label="displayName"
			@search="onSearch" />
		
		<NcButton :disabled="!selectedUser"
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
	findUserMemberOptions: {
		type: Function,
		required: false,
		default: async () => [],
	},
});

const emit = defineEmits(["add-member"]);

const selectedUser = ref(null);

const options = ref([]);

const onSearch = (search) => {
	props.findUserMemberOptions(search).then((newOptions) => {
		options.value = newOptions;
	});
};

const onSave = () => {
	emit("add-member", selectedUser.value.id);
};

</script>
<style scoped>
.input-row {
	display: flex;
	justify-content: flex-start;
}
</style>
