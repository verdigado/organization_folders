<script setup>
import { ref, computed, watch } from "vue";
import { getCurrentUser } from "@nextcloud/auth";
import { useRouter } from "vue2-helpers/vue-router";
import { translate as t, translatePlural as n } from "@nextcloud/l10n";

import NcActions from "@nextcloud/vue/components/NcActions";
import NcActionButton from "@nextcloud/vue/components/NcActionButton";
import NcTextField from "@nextcloud/vue/components/NcTextField";

import Pencil from "vue-material-design-icons/Pencil.vue";

import Section from "../components/Section.vue";
import SectionHeader from "../components/SectionHeader.vue";
import HeaderButtonGroup from "../components/SectionHeaderButtonGroup.vue";
import Principal from "../components/Principal.vue";
import ResourceList from "../components/ResourceList.vue";
import CreateResourceButton from "../components/CreateResourceButton.vue";
import MembersList from "../components/MemberList/MembersList.vue";
import CreateMemberButton from "../components/CreateMemberButton/CreateMemberButton.vue";

import ModalView from '../ModalView.vue';

import api from "../api.js";
import { useOrganizationProvidersStore } from "../stores/organization-providers.js";
import { validOrganizationFolderName } from "../helpers/validation.js";

const props = defineProps({
	organizationFolderId: {
	  type: Number,
	  required: true,
  },
});

const organizationProviders = useOrganizationProvidersStore();

organizationProviders.initialize();

const organizationFolder = ref(null);
const loading = ref(false);
const currentOrganizationFolderName = ref(false);

const userIsAdmin = ref(getCurrentUser().isAdmin);

const memberPermissionLevelOptions = [
  { label: "Mitglied", value: 1 },
  { label: "Manager", value: 2 },
  { label: "Admin", value: 3 },
];

const neededOrganizationFolderIncludes = "model+permissions+members+resources";

const router = useRouter();

const organizationFolderNameValid = computed(() => {
	return validOrganizationFolderName(currentOrganizationFolderName.value); 
});

const organizationFolderPermissionsLimited = computed(() => {
	return organizationFolder.value?.permissions?.level === "limited"; 
});

watch(() => props.organizationFolderId, async (newOrganizationFolderId) => {
	loading.value = true;
	organizationFolder.value = await api.getOrganizationFolder(newOrganizationFolderId, neededOrganizationFolderIncludes);
	currentOrganizationFolderName.value = organizationFolder.value.name;
	loading.value = false;
}, { immediate: true });

const saveName = async () => {
    organizationFolder.value = await api.updateOrganizationFolder(organizationFolder.value.id, { name: currentOrganizationFolderName.value }, neededOrganizationFolderIncludes);
};

const addMember = async (principalType, principalId) => {
	organizationFolder.value.members.push(await api.createOrganizationFolderMember(organizationFolder.value.id, {
		permissionLevel: api.OrganizationFolderMemberPermissionLevels.MEMBER,
		principalType,
		principalId,
	}));
};

const updateMember = async (organizationFolderMemberId, updateOrganiationFolderMemberDto) => {
	const member = await api.updateOrganizationFolderMember(organizationFolderMemberId, updateOrganiationFolderMemberDto);
	organizationFolder.value.members = organizationFolder.value.members.map((m) => m.id === member.id ? member : m);
};

const deleteMember = async (organizationFolderMemberId) => {
	await api.deleteOrganizationFolderMember(organizationFolderMemberId);
	organizationFolder.value.members = organizationFolder.value.members.filter((m) => m.id !== organizationFolderMemberId);
};

const resourceClicked = (resource) => {
	router.push({
		path: '/resource/' + resource.id,
	});
};

const backButtonClicked = () => {
	if(userIsAdmin) {
		router.push({
			path: '/organizationFolders',
		});
	}
};

const createResource = async (type, name) => {
	const newResource = await api.createResource({
		type,
		organizationFolderId: organizationFolder.value.id,
		name,
		active: true,
		inheritManagers: true,

		membersAclPermission: 0,
		managersAclPermission: 31,
		inheritedAclPermission: 0,
	});

	organizationFolder.value?.resources.push(newResource);

	resourceClicked(newResource);
}

const findGroupMemberOptions = () => {
	// api route for this does not exist yet
	return [];
}

const findUserMemberOptions = () => {
	// api route for this does not exist yet
	return [];
}

const openOrganizationPicker = () => {

};

const permissionLevelExplanation = t(
	"organization_folders",
	"Managers have access to the settings of top-level resources with manager inheritance enabled. Admins have access to the settings of all resources, regardless of their inheritance setting.",
	{},
	{ escape: false }
);

</script>
<template>
	<ModalView
		:has-back-button="userIsAdmin"
		:has-next-step-button="false"
		:has-last-step-button="false"
		:title="'Organization Folder Settings'"
		:loading="loading"
		v-slot=""
		@back-button-pressed="backButtonClicked">
		<Section>
			<template #header>
				<SectionHeader text="Eigenschaften"></SectionHeader>
			</template>
			<NcTextField :value.sync="currentOrganizationFolderName"
				:disabled="organizationFolderPermissionsLimited"
				:error="!organizationFolderNameValid"
				:label-visible="!organizationFolderNameValid"
				:label-outside="true"
				:helper-text="organizationFolderNameValid ? '' : 'Ungültiger Name'"
				label="Name"
				:show-trailing-button="currentOrganizationFolderName !== organizationFolder.name"
				trailing-button-icon="arrowRight"
				style=" --color-border-maxcontrast: #949494;"
				@trailing-button-click="saveName"
				@blur="() => currentOrganizationFolderName = currentOrganizationFolderName.trim()"
				@keyup.enter="saveName" />
		</Section>
		<Section v-if="!organizationFolderPermissionsLimited">
			<template #header>
				<SectionHeader text="Organisation"></SectionHeader>
			</template>
			<div style="display: flex; flex-direction: row; align-items: center;">
				<Principal :principal="organizationFolder?.organizationPrincipal" />
				<NcActions>
					<NcActionButton @click="openOrganizationPicker">
						<template #icon>
							<Pencil :size="20" />
						</template>
						Edit
					</NcActionButton>
				</NcActions>
			</div>
		</Section>
		<Section v-if="!organizationFolderPermissionsLimited">
			<template #header>
				<HeaderButtonGroup text="Members">
					<CreateMemberButton :organizationProviders="organizationProviders.providers"
						:permission-level-options="memberPermissionLevelOptions"
                        :enable-user-type="false"
						:find-group-member-options="findGroupMemberOptions"
						:find-user-member-options="findUserMemberOptions"
						@add-member="addMember" />
				</HeaderButtonGroup>
			</template>
			<MembersList :members="organizationFolder?.members"
				:permission-level-options="memberPermissionLevelOptions"
				:permission-level-explanation="permissionLevelExplanation"
				@update-member="updateMember"
				@delete-member="deleteMember" />
		</Section>
		<HeaderButtonGroup text="Resourcen">
			<CreateResourceButton @create="createResource" />
		</HeaderButtonGroup>
		<ResourceList :resources="organizationFolder?.resources" :enable-search="true" @click:resource="resourceClicked" />
	</ModalView>
</template>