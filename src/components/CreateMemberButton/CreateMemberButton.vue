<script setup>
import NcActions from "@nextcloud/vue/components/NcActions";
import NcActionButton from "@nextcloud/vue/components/NcActionButton";
import NcDialog from "@nextcloud/vue/components/NcDialog";
import NcButton from "@nextcloud/vue/components/NcButton";
import NcLoadingIcon from "@nextcloud/vue/components/NcLoadingIcon";

import UserPrincipalSelector from "./UserPrincipalSelector.vue";
import GroupPrincipalSelector from "./GroupPrincipalSelector.vue";
import RoleOrMemberPrincipalSelector from "./RoleOrMemberPrincipalSelector.vue";

import Plus from "vue-material-design-icons/Plus.vue";
import ArrowRight from "vue-material-design-icons/ArrowRight.vue";

import { computed, ref } from 'vue';

const props = defineProps({
	enableUserType: {
		type: Boolean,
		default: true,
	},
	enableGroupType: {
		type: Boolean,
		default: true,
	},
	enableRoleType: {
		type: Boolean,
		default: true,
	},
	organizationProviders: {
		type: Array,
		required: false,
		default: [],
	},
	permissionLevelOptions: {
		type: Array,
		required: true,
	},
	findGroupMemberOptions: {
		type: Function,
		required: false,
		default: async () => [],
	},
	findUserMemberOptions: {
		type: Function,
		required: false,
		default: async () => [],
	},
});

const emit = defineEmits(["add-member"]);

const newMemberType = ref(null);
const newMemberAdditionalParameters = ref({});
const dialogOpen = ref(false);
const loading = ref(false);

const currentSelectedPrincipalType = ref(null);
const currentSelectedPrincipalId = ref(null);

const title = computed(() => {
	if(newMemberType.value === 'USER') {
		return t("organization_folders", "Select an account");
	} else if (newMemberType.value === 'GROUP') {
		return t("organization_folders", "Select a group");
	} else if (newMemberType.value === 'ORGANIZATION_MEMBER_OR_ROLE') {
		return t("organization_folders", "Select an organization role");
	}
	return "";
});

const selectNewMemberType = (type, additionalParameters = {}) => {
	dialogOpen.value = false;
	newMemberType.value = type;
	newMemberAdditionalParameters.value = additionalParameters;
	if(type) {
		dialogOpen.value = true;
	}
};

const selected = (principalType, principalId) => {
	currentSelectedPrincipalType.value = principalType;
	currentSelectedPrincipalId.value = principalId;
}

const dialogSubmit = () => {
	loading.value = true;
	emit("add-member", currentSelectedPrincipalType.value, currentSelectedPrincipalId.value, (success) => {
		if(success) {
			dialogOpen.value = false;
			newMemberType.value = null;
			newMemberAdditionalParameters.value = {};
			currentSelectedPrincipalType.value = null;
			currentSelectedPrincipalId.value = null;
		}

		loading.value = false;
	});
};

const dialogClose = () => {
	dialogOpen.value = false;
	newMemberType.value = null;
	newMemberAdditionalParameters.value = {};
	currentSelectedPrincipalType.value = null;
	currentSelectedPrincipalId.value = null;
};

const dialogUpdate = (open) => {
	dialogOpen.value = open;
	if(!open) {
		dialogClose();
	}
};

</script>

<template>
	<div>
		<NcActions type="secondary">
			<template #icon>
				<Plus :size="20" />
			</template>
			<NcActionButton icon="icon-user" close-after-click v-if="props.enableUserType" @click="selectNewMemberType('USER')">
				{{ t("organization_folders", "Add account") }}
			</NcActionButton>
			<NcActionButton icon="icon-group" close-after-click v-if="props.enableGroupType" @click="selectNewMemberType('GROUP')">
				{{ t("organization_folders", "Add group") }}
			</NcActionButton>
			<NcActionButton v-for="organizationProvider of (props.enableGroupType ? organizationProviders : [])"
				:key="organizationProvider.id"
				icon="icon-group"
				close-after-click
				@click="selectNewMemberType('ORGANIZATION_MEMBER_OR_ROLE', { organizationProvider: organizationProvider.id })">
				{{ t(
						"organization_folders",
						"Add {organizationProvider} organization members or role",
						{
							organizationProvider: organizationProvider.friendlyName,
						}
					)
				}}
			</NcActionButton>
		</NcActions>
		<NcDialog :open="dialogOpen"
			:name="title"
			size="large"
			class="create-member-modal"
			dialogClasses="create-member-dialog"
			contentClasses="create-member-content"
			:class="{ fullWidthDialog: newMemberType === 'ORGANIZATION_MEMBER_OR_ROLE' }"
			@update:open="dialogUpdate">
			<div style="margin: 20px;">
				<UserPrincipalSelector v-if="newMemberType === 'USER'"
					:find-user-member-options="findUserMemberOptions"
					@selected="selected" />
				<GroupPrincipalSelector v-else-if="newMemberType === 'GROUP'"
					:find-group-member-options="findGroupMemberOptions"
					@selected="selected" />
				<RoleOrMemberPrincipalSelector v-else-if="newMemberType === 'ORGANIZATION_MEMBER_OR_ROLE'"
					:organization-provider="newMemberAdditionalParameters?.organizationProvider"
					@selected="selected" />
			</div>
			<template #actions>
				<NcButton :disabled="!currentSelectedPrincipalId || loading" alignment="center-reverse" @click="dialogSubmit">
					<template #icon>
						<NcLoadingIcon v-if="loading" />
						<ArrowRight v-else :size="20" />
					</template>
					{{ t("organization_folders", "Add") }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>
<style>
.create-member-modal .modal-container {
	width: unset !important;
}

.fullWidthDialog .create-member-dialog {
	min-width: 75vw;
}
</style>