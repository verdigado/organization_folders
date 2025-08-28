<!--
  - @copyright Copyright (c) 2024 Jonathan Treffler <jonathan.treffler@verdigado.com>
  -
  - @author Jonathan Treffler <jonathan.treffler@verdigado.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<script setup>
import { ref, computed, watch, nextTick } from "vue";
import { formatFileSize } from "@nextcloud/files";
import { getCurrentUser } from "@nextcloud/auth";
import { useRouter } from "vue2-helpers/vue-router";
import { translate as t, translatePlural as n } from "@nextcloud/l10n";

import NcActions from "@nextcloud/vue/components/NcActions";
import NcActionButton from "@nextcloud/vue/components/NcActionButton";
import NcTextField from "@nextcloud/vue/components/NcTextField";

import Pencil from "vue-material-design-icons/Pencil.vue";

import Section from "../components/Section.vue";
import SectionHeader from "../components/SectionHeader.vue";
import SubSection from "../components/SubSection.vue";
import SubSectionHeader from "../components/SubSectionHeader.vue";
import HeaderButtonGroup from "../components/SectionHeaderButtonGroup.vue";
import EditCancelSaveButtons from "../components/EditCancelSaveButtons.vue";
import Hierarchy from "../components/Hierarchy.vue";
import ResourceList from "../components/ResourceList.vue";
import CreateResourceButton from "../components/CreateResourceButton.vue";
import MembersList from "../components/MemberList/MembersList.vue";
import CreateMemberButton from "../components/CreateMemberButton/CreateMemberButton.vue";
import QuotaSelector from "../components/QuotaSelector.vue";

import ModalView from '../ModalView.vue';

import api from "../api.js";
import { useOrganizationProvidersStore } from "../stores/organization-providers.js";
import { validOrganizationFolderName } from "../helpers/validation.js";
import { formatQuotaSize } from "../helpers/file-size-helpers.js"

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
const currentOrganizationFolderQuota = ref(false);

const nameEditActive = ref(false);
const saveNameLoading = ref(false);

const quotaEditActive = ref(false);
const saveQuotaLoading = ref(false);

const userIsAdmin = ref(getCurrentUser().isAdmin);

const memberPermissionLevelOptions = [
  // TRANSLATORS This a permission level of members of organization folders and resources
  { label: t("organization_folders", "Member"), value: 1 },
  // TRANSLATORS This a permission level of members of organization folders and resources
  { label: t("organization_folders", "Manager"), value: 2 },
  // TRANSLATORS This a permission level of members of organization folders, this is not about nextcloud system administrators
  { label: t("organization_folders", "Admin"), value: 3 },
];

const neededOrganizationFolderIncludes = "model+permissions+quotaUsed+members+resources";

const router = useRouter();

const organizationFolderNameValid = computed(() => {
	return validOrganizationFolderName(currentOrganizationFolderName.value); 
});

const organizationFolderPermissionsLimited = computed(() => {
	return organizationFolder.value?.permissions?.level === "limited"; 
});

const organizationFullHierarchyNames = computed(() => {
	let result = [];

	if(organizationFolder.value.organizationFullHierarchy) {
		result.push(organizationFolder.value.organizationProviderFriendlyName);
		
		for(let organization of organizationFolder.value.organizationFullHierarchy) {
			result.push(organization.friendlyName);
		}
	}

	return result;
})

const quotaUsedPercent = computed(() => {
	if(organizationFolder.value?.quota > 0) {
		return (organizationFolder.value?.quotaUsed / organizationFolder.value?.quota) * 100;
	} else {
		return 0;
	}
});

const quotaHumanReadable = computed(() => {
	return formatQuotaSize(organizationFolder.value.quota);
});

const quotaUsedHumanReadable = computed(() => {
	if(organizationFolder.value.quotaUsed >= 0) {
		return formatFileSize(organizationFolder.value.quotaUsed);
	} else {
		return "?";
	}
});

watch(() => props.organizationFolderId, async (newOrganizationFolderId) => {
	loading.value = true;
	organizationFolder.value = await api.getOrganizationFolder(newOrganizationFolderId, neededOrganizationFolderIncludes);
	loading.value = false;
}, { immediate: true });

const nameTextField = ref(null);

const editName = () => {
	currentOrganizationFolderName.value = organizationFolder.value.name;
	nameEditActive.value = true;
	nextTick(() => {
		nameTextField.value?.focus();
	});
};

const saveName = async () => {
	saveNameLoading.value = true;
	try {
		organizationFolder.value = await api.updateOrganizationFolder(organizationFolder.value.id, { name: currentOrganizationFolderName.value }, neededOrganizationFolderIncludes);
	} finally {
		saveNameLoading.value = false;
		nameEditActive.value = false;
	}
};

const cancelNameEdit = () => {
	nameEditActive.value = false;
};

const editQuota = () => {
	currentOrganizationFolderQuota.value = organizationFolder.value.quota;
	quotaEditActive.value = true;
};

const saveQuota = async () => {
	saveQuotaLoading.value = true;
	try {
		organizationFolder.value = await api.updateOrganizationFolder(organizationFolder.value.id, { quota: currentOrganizationFolderQuota.value }, neededOrganizationFolderIncludes);
	} finally {
		saveQuotaLoading.value = false;
		quotaEditActive.value = false;
	}
};

const cancelQuotaEdit = () => {
	quotaEditActive.value = false;
};

const addMember = async (principalType, principalId, callback) => {
	try {
		organizationFolder.value.members.push(await api.createOrganizationFolderMember(organizationFolder.value.id, {
			permissionLevel: api.OrganizationFolderMemberPermissionLevels.MEMBER,
			principalType,
			principalId,
		}));
		callback(true);
	} catch (error) {
		callback(false, error);
	}
};

const updateMember = async (organizationFolderMemberId, updateOrganiationFolderMemberDto, callback) => {
	const member = await api.updateOrganizationFolderMember(organizationFolderMemberId, updateOrganiationFolderMemberDto);
	organizationFolder.value.members = organizationFolder.value.members.map((m) => m.id === member.id ? member : m);
	callback();
};

const deleteMember = async (organizationFolderMemberId, callback) => {
	await api.deleteOrganizationFolderMember(organizationFolderMemberId);
	callback();
	organizationFolder.value.members = organizationFolder.value.members.filter((m) => m.id !== organizationFolderMemberId);
};

const resourceClicked = (resource) => {
	router.push({
		path: '/organizationFolder/' + resource.organizationFolderId + '/resource/' + resource.id,
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
);

</script>
<template>
	<ModalView
		:has-back-button="userIsAdmin"
		:has-next-step-button="false"
		:has-previous-step-button="false"
		:title="t('organization_folders', 'Organization Folder Settings')"
		:loading="loading"
		v-slot=""
		@back-button-pressed="backButtonClicked">
		<Section>
			<template #header>
				<SectionHeader :text="t('organization_folders', 'Settings')"></SectionHeader>
			</template>

			<SubSection>
				<template #header>
					<SubSectionHeader :text="t('organization_folders', 'Name')" />
				</template>

				<div style="display: flex; flex-direction: row; align-items: center; column-gap: 3px;">
					<p v-if="!nameEditActive" style="padding-left: 10px;">{{ organizationFolder.name }}</p>
					<NcTextField v-else
						ref="nameTextField"
						:value.sync="currentOrganizationFolderName"
						:error="!organizationFolderNameValid"
						:helper-text="organizationFolderNameValid ? '' : t('organization_folders', 'Invalid name')"
						:label="t('organization_folders', 'Name')"
						:label-outside="true"
						style="--color-border-maxcontrast: #949494;"
						@trailing-button-click="saveName"
						@blur="() => currentOrganizationFolderName = currentOrganizationFolderName.trim()"
						@keyup.enter="saveName"
						@keydown.esc.stop.prevent
						@keyup.esc.stop.prevent="cancelNameEdit" />
					<EditCancelSaveButtons v-if="!organizationFolderPermissionsLimited"
						:edit-active="nameEditActive"
						:loading="saveNameLoading"
						@edit="editName"
						@cancel="cancelNameEdit"
						@save="saveName" />
				</div>
			</SubSection>

			<SubSection>
				<template #header>
					<SubSectionHeader :text="t('organization_folders', 'Storage Quota')" />
				</template>
				<div style="display: flex; flex-direction: row; align-items: center; column-gap: 3px;">
					<p v-if="!quotaEditActive" style="padding-left: 10px;">
						{{ quotaHumanReadable }}
						<span v-tooltip="t('organization_folders', '{usedStorage}/{availableStorage} used', { usedStorage: quotaUsedHumanReadable, availableStorage: quotaHumanReadable })">
							{{ t('organization_folders', '({percent}% used)', { percent: (quotaUsedPercent >= 0) ? quotaUsedPercent.toFixed(2) : "?" }) }}
						</span>
					</p>
					<QuotaSelector v-else
						v-model="currentOrganizationFolderQuota" />
						
					<EditCancelSaveButtons v-if="!organizationFolderPermissionsLimited"
						:edit-active="quotaEditActive"
						:loading="saveQuotaLoading"
						@edit="editQuota"
						@cancel="cancelQuotaEdit"
						@save="saveQuota" />
				</div>
			</SubSection>

			<SubSection>
				<template #header>
					<SubSectionHeader :text="t('organization_folders', 'Organization')" />
				</template>

				<div style="display: flex; flex-direction: row; align-items: center; column-gap: 3px; padding-left: 10px;">
					<Hierarchy v-if="organizationFolder?.organizationFullHierarchy"
						:hierarchy-names="organizationFullHierarchyNames" />
					<p v-else>{{ t('organization_folders', 'No organization assigned') }}</p>
					<NcActions v-if="!organizationFolderPermissionsLimited">
						<NcActionButton @click="openOrganizationPicker">
							<template #icon>
								<Pencil :size="20" />
							</template>
							{{ t("organization_folders", "Edit") }}
						</NcActionButton>
					</NcActions>
				</div>
			</SubSection>
		</Section>
		<Section v-if="!organizationFolderPermissionsLimited">
			<template #header>
				<HeaderButtonGroup :text="t('organization_folders', 'Members')">
					<CreateMemberButton :organizationProviders="organizationProviders.providers"
						:permission-level-options="memberPermissionLevelOptions"
                        :enable-user-type="false"
						:find-group-member-options="findGroupMemberOptions"
						:find-user-member-options="findUserMemberOptions"
						:initial-role-organization-path="organizationFolder?.organizationFullHierarchy ?? []"
						@add-member="addMember" />
				</HeaderButtonGroup>
			</template>
			<MembersList :members="organizationFolder?.members"
				:permission-level-options="memberPermissionLevelOptions"
				:permission-level-explanation="permissionLevelExplanation"
				@update-member="updateMember"
				@delete-member="deleteMember" />
		</Section>
		<HeaderButtonGroup :text="t('organization_folders', 'Resources')">
			<CreateResourceButton @create="createResource" />
		</HeaderButtonGroup>
		<ResourceList :resources="organizationFolder?.resources" :enable-search="true" @click:resource="resourceClicked" />
	</ModalView>
</template>