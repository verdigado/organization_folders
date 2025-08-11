<script setup>
import { ref, watch, computed } from "vue";
import { loadState } from '@nextcloud/initial-state';
import { useRouter } from 'vue2-helpers/vue-router';
import { translate as t, translatePlural as n } from "@nextcloud/l10n";

import NcLoadingIcon from "@nextcloud/vue/components/NcLoadingIcon";
import NcCheckboxRadioSwitch from "@nextcloud/vue/components/NcCheckboxRadioSwitch";
import NcButton from "@nextcloud/vue/components/NcButton";
import NcTextField from "@nextcloud/vue/components/NcTextField";
import NcNoteCard from "@nextcloud/vue/components/NcNoteCard";
import NcDialog from "@nextcloud/vue/components/NcDialog";

import BackupRestore from "vue-material-design-icons/BackupRestore.vue";
import Delete from "vue-material-design-icons/Delete.vue";

import HeaderButtonGroup from "../components/SectionHeaderButtonGroup.vue";
import Section from "../components/Section.vue";
import SectionCollapseable from "../components/SectionCollapseable.vue";
import SectionHeader from "../components/SectionHeader.vue";
import MembersList from "../components/MemberList/MembersList.vue";
import Permissions from "../components/Permissions/index.js";
import ConfirmDeleteDialog from "../components/ConfirmDeleteDialog.vue";
import ResourceList from "../components/ResourceList.vue";
import CreateResourceButton from "../components/CreateResourceButton.vue";
import CreateMemberButton from "../components/CreateMemberButton/CreateMemberButton.vue";
import UnmanagedSubfoldersList from "../components/UnmanagedSubfoldersList.vue";
import UserPrincipalSelector from "../components/UserPrincipalSelector.vue";
import PermissionsReport from "../components/PermissionsReport/PermissionsReport.vue";
import UserPermissionsReport from "../components/UserPermissionsReport/UserPermissionsReport.vue";

import ModalView from '../ModalView.vue';

import api from "../api.js";
import { useOrganizationProvidersStore } from "../stores/organization-providers.js";
import { validResourceName } from "../helpers/validation.js";

const props = defineProps({
	organizationFolderId: {
		type: Number,
		required: true,
	},
	resourceId: {
		type: Number,
		required: true,
	},
});

const organizationProviders = useOrganizationProvidersStore();

organizationProviders.initialize();

const resourceApiIncludes = "model+permissions+members+subresources+unmanagedSubfolders";

const resource = ref(null);
const loading = ref(false);
const resourceActiveLoading = ref(false);

const permissionsReportOpen = ref(false);
const permissionsReportLoading = ref(true);
const permissionsReportPage = ref("overview");

const permissionsReport = ref(null);
const userPermissionsReport = ref(null);

const currentResourceName = ref(false);

const resourceNameValid = computed(() => {
    return validResourceName(currentResourceName.value);
});

const saveName = async () => {
    resource.value = await api.updateResource(resource.value.id, { name: currentResourceName.value }, resourceApiIncludes);
};

const saveInheritManagers = async (inheritManagers) => {
    resource.value = await api.updateResource(resource.value.id, { inheritManagers }, resourceApiIncludes);
};

const resourcePermissionsLimited = computed(() => {
    return resource.value?.permissions?.level === "limited";
});

watch(() => props.resourceId, async (newResourceId) => {
    loading.value = true;
    resource.value = await api.getResource(newResourceId, resourceApiIncludes);
    currentResourceName.value = resource.value.name;
	permissionsReport.value = undefined;
	userPermissionsReport.value = undefined;
    loading.value = false;
}, { immediate: true });

const saveActive = async (active) => {
    resourceActiveLoading.value = true;
    resource.value = await api.updateResource(resource.value.id, { active }, resourceApiIncludes);
    resourceActiveLoading.value = false;
};

const savePermission = async ({ field, value }) => {
    resource.value = await api.updateResource(resource.value.id, {
	  [field]: value,
	}, resourceApiIncludes);
};

const deleteResource = async (closeDialog) => {
	await api.deleteResource(resource.value.id);
	closeDialog();
	backButtonClicked();
}

const switchToSnapshotRestoreView = ()  => {
	router.push({
		path: '/organizationFolder/' + props.organizationFolderId + '/resource/' + props.resourceId + "/restoreFromSnapshot",
	});
};

const addMember = async (principalType, principalId, callback) => {
	try {
		resource.value.members.push(await api.createResourceMember(resource.value.id, {
			permissionLevel: api.ResourceMemberPermissionLevels.MEMBER,
			principalType,
			principalId,
		}));
		callback(true);
	} catch (error) {
		callback(false, error);
	}
};

const updateMember = async (memberId, updateResourceMemberDto) => {
	const member = await api.updateResourceMember(memberId, updateResourceMemberDto);
	resource.value.members = resource.value.members.map((m) => m.id === member.id ? member : m);
};

const deleteMember = async (memberId) => {
	await api.deleteResourceMember(memberId);
	resource.value.members = resource.value.members.filter((m) => m.id !== memberId);
};

const snapshotIntegrationActive = loadState('organization_folders', 'snapshot_integration_active', false);
const subfoldersEnabled = loadState('organization_folders', 'subresources_enabled', false);

const router = useRouter();

const subResourceClicked = (resource) => {
	router.push({
		path: '/organizationFolder/' + resource.organizationFolderId + '/resource/' + resource.id,
	});
};

const backButtonClicked = () => {
	if(resource.value?.parentResource) {
		router.push({
			path: '/organizationFolder/' + props.organizationFolderId + '/resource/' + resource.value.parentResource,
		});
	} else {
		router.push({
			path: '/organizationFolder/' + props.organizationFolderId
		});
	}
};

const createSubResource = async (type, name) => {
	resource.value.subResources.push(await api.createResource({
		type,
		organizationFolderId: resource.value.organizationFolderId,
		name,
		parentResourceId: resource.value.id,
		active: true,
		inheritManagers: true,

		membersAclPermission: 0,
		managersAclPermission: 31,
		inheritedAclPermission: 1,
	}));
}

const findGroupMemberOptions = (search) => {
	return api.findGroupResourceMemberOptions(resource.value.id, search);
};

const findUserMemberOptions = (search) => {
	return api.findUserResourceMemberOptions(resource.value.id, search);
};

const promoteUnmanagedSubfolder = async (subfolderName, callback) => {
	try {
		resource.value.subResources.push(await api.promoteUnmanagedResourceSubfolder(resource.value.id, subfolderName));
		resource.value.unmanagedSubfolders = resource.value.unmanagedSubfolders.filter((name) => name !== subfolderName);
		callback(true);
	} catch (error) {
		callback(false, error);
	}
};

const title = computed(() =>{
	if(resource.value?.type === api.ResourceTypes.FOLDER) {
		// TRANSLATORS This is a modal header for the settings of a folder called folderName
		return t(
			"organization_folders",
			'Folder Management "{folderName}"',
			{
				folderName: resource.value?.name,
			}
		);
	} else {
		return t("organization_folders", "Settings");
	}
});

const memberPermissionLevelOptions = computed(() => {
	if(resource.value?.type === api.ResourceTypes.FOLDER) {
		return [
			// TRANSLATORS This a permission level of members of folder resources
			{ label: t("organization_folders", "Folder member"), value: 1 },
			// TRANSLATORS This a permission level of members of folder resources
			{ label: t("organization_folders", "Folder manager"), value: 2 },
		];
	} else {
		return [
			// TRANSLATORS This a permission level of members of organization folders and resources
			{ label: t("organization_folders", "Member"), value: 1 },
			// TRANSLATORS This a permission level of members of organization folders and resources
			{ label: t("organization_folders", "Manager"), value: 2 },
		];
	}
});

const permissionLevelExplanation = computed(() => {
	if(resource.value?.type === api.ResourceTypes.FOLDER) {
		return t("organization_folders", "Managers have access to the settings of this folder");
	} else {
		return "";
	}
});

const noPermissionExplanation = computed(() => {
	if(resource.value?.type === api.ResourceTypes.FOLDER) {
		return t("organization_folders", "You do not have the permissions to manage this folder");
	} else {
		return "";
	}
});

const deleteResourceText = computed(() => {
	if(resource.value?.type === api.ResourceTypes.FOLDER) {
		return t("organization_folders", "Delete folder");
	} else {
		return "";
	}
});

const deleteResourceExplanation = computed(() => {
	if(resource.value?.type === api.ResourceTypes.FOLDER) {
		if(resource.value?.subResources.length === 0) {
			return t(
				"organization_folders",
				'You are about to delete the folder "{folderName}". Are you sure you want to proceed?',
				{
					folderName: resource.value?.name,
				}
			);
		} else {
			return n(
				"organization_folders",
				'You are about to delete the folder "{folderName}" and its %n sub-resource. Are you sure you want to proceed?',
				'You are about to delete the folder "{folderName}" and its %n sub-resources. Are you sure you want to proceed?',
				resource.value?.subResources.length,
				{
					folderName: resource.value?.name,
				}
			);
		}
	} else {
		return "";
	}
});

const permissionsReportExplanation = computed(() => {
	if(permissionsReportPage.value === "overview") {
		return t("organization_folders", "This shows all permissions that were granted to this resource, including the ones inherited from parent folders.<br>Note that persons can qualify for multiple of these permissions, in that case all the permissions get added up. This is especially important for the permissions of individual people listed here, as those are only the permissions granted to them personally and they might be part of groups that give them further permissions. To get the exact permissions any specific person has with their current groups, memberships and roles switch to the second tab.", { escape: false });
	} else {
		return t("organization_folders", "Select a person to get all permissions they qualify for with their current groups, memberships and roles.");
	}
})

const openPermissionsReport = async () => {
	permissionsReportLoading.value = true;
	permissionsReportOpen.value = true;
	permissionsReportPage.value = "overview";
	permissionsReport.value = await api.getResourcePermissionsReport(resource.value.id);
	userPermissionsReport.value = undefined;
	permissionsReportLoading.value = false;
};

const findUserPermissionsReportOptions = (search) => {
	return api.findResourceUserPermissionsReportOptions(resource.value.id, search);
};

const selectedPermissionsReportUser = async (principalType, principalId) => {
	console.log("selected userPrincipal " + principalId);
	if(principalId) {
		permissionsReportLoading.value = true;
		userPermissionsReport.value = await api.getResourceUserPermissionsReport(resource.value.id, principalId);
		permissionsReportLoading.value = false;
	} else {
		userPermissionsReport.value = null;
	}
};

</script>

<template>
    <ModalView
		:has-back-button="true"
		:has-next-step-button="false"
		:has-previous-step-button="false"
		:title="title"
		:loading="loading"
		v-slot=""
		@back-button-pressed="backButtonClicked">
		<NcNoteCard v-if="resourcePermissionsLimited"
			type="info"
			:text="noPermissionExplanation" />
		<Section>
			<template #header>
				<SectionHeader :text="t('organization_folders', 'Folder Name')"></SectionHeader>
			</template>
			<NcTextField :value.sync="currentResourceName"
				:disabled="resourcePermissionsLimited"
				:class="{ 'not-allowed-cursor': resourcePermissionsLimited }"
				:error="!resourceNameValid"
				:label-visible="!resourceNameValid"
				:label-outside="true"
				:helper-text="resourceNameValid ? '' : t('organization_folders', 'Invalid name')"
				:label="t('organization_folders', 'Name')"
				:show-trailing-button="currentResourceName !== resource.name"
				trailing-button-icon="arrowRight"
				style=" --color-border-maxcontrast: #949494;"
				@trailing-button-click="saveName"
				@blur="() => currentResourceName = currentResourceName.trim()"
				@keyup.enter="saveName" />
			<NcCheckboxRadioSwitch
				:checked="resource.inheritManagers"
				:disabled="resourcePermissionsLimited"
				:class="{ 'not-allowed-cursor': resourcePermissionsLimited }"
				style="margin-top: 12px;"
				@update:checked="saveInheritManagers">
				{{ t("organization_folders", "Inherit managers from the level above") }}
			</NcCheckboxRadioSwitch>
		</Section>
		<Section v-if="!resourcePermissionsLimited">
			<template #header>
				<SectionHeader :text="t('organization_folders', 'Permissions')"></SectionHeader>
			</template>
			<Permissions :resource="resource"
				@permissionUpdated="savePermission" />
		</Section>
		<Section v-if="!resourcePermissionsLimited">
			<template #header>
				<HeaderButtonGroup :text="t('organization_folders', 'Members')">
					<CreateMemberButton :organizationProviders="organizationProviders.providers"
						:permission-level-options="memberPermissionLevelOptions"
						:find-group-member-options="findGroupMemberOptions"
						:find-user-member-options="findUserMemberOptions"
						@add-member="addMember" />
				</HeaderButtonGroup>
			</template>
			<MembersList :members="resource?.members"
				:permission-level-options="memberPermissionLevelOptions"
				:permission-level-explanation="permissionLevelExplanation"
				@update-member="updateMember"
				@delete-member="deleteMember" />
		</Section>
		<Section v-if="!resourcePermissionsLimited">
			<template #header>
				<SectionHeader :text="t('organization_folders', 'Management Actions')"></SectionHeader>
			</template>
			<div class="settings-group">
				<NcButton @click="openPermissionsReport">
					{{ t("organization_folders", "Show Permissions Overview") }}
				</NcButton>
				<NcDialog :open.sync="permissionsReportOpen"
					:name="t('organization_folders', 'Permissions Overview')"
					size="large">
					<div style="display: flex; justify-content: center;">
						<NcCheckboxRadioSwitch
							:button-variant="true"
							v-model="permissionsReportPage"
							value="overview"
							name="permissions_report_page"
							type="radio"
							button-variant-grouped="horizontal">
							{{ t("organization_folders", "Permissions overview") }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch
							:button-variant="true"
							v-model="permissionsReportPage"
							value="user"
							name="permissions_report_page"
							type="radio"
							button-variant-grouped="horizontal">
							{{ t("organization_folders", "Permissions of person") }}
						</NcCheckboxRadioSwitch>
					</div>

					<p style="margin-top: 20px; margin-bottom: 20px;" v-html="permissionsReportExplanation"></p>
					
					<UserPrincipalSelector v-if="permissionsReportPage === 'user'"
						:find-user-member-options="findUserPermissionsReportOptions"
						style="margin-bottom: 20px;"
						@selected="selectedPermissionsReportUser" />
					
					<NcLoadingIcon v-if="permissionsReportLoading" :size="64" style="margin-top: 30%; margin-bottom: 30%;" />
					<PermissionsReport v-else-if="permissionsReportPage === 'overview'" :resource="resource" :permissions-report="permissionsReport" />
					<UserPermissionsReport v-else-if="userPermissionsReport" :resource="resource" :user-permissions-report="userPermissionsReport" />
				</NcDialog>
				<NcButton v-if="snapshotIntegrationActive" @click="switchToSnapshotRestoreView">
					<template #icon>
						<BackupRestore />
					</template>
					{{ t("organization_folders", "Restore files from a backup") }}
				</NcButton>
				<div class="resource-active-button">
					<NcCheckboxRadioSwitch :checked="resource.active"
						:loading="resourceActiveLoading"
						type="checkbox"
						@update:checked="saveActive">
						{{ t("organization_folders", "Resource active") }}
					</NcCheckboxRadioSwitch>
				</div>
				<ConfirmDeleteDialog :title="deleteResourceText"
					:loading="loading"
					:match-text="resource.name">
					<template #activator="{ open }">
						<NcButton v-tooltip="resource.active ? t('organization_folders', 'Only deactivated resources can be deleted') : undefined"
							style="height: 52px;"
							:disabled="resource.active"
							type="error"
							@click="open">
							{{ deleteResourceText }}
						</NcButton>
					</template>
					<template #content>
						<p style="margin: 1rem 0 1rem 0">
							{{ deleteResourceExplanation }}
						</p>
					</template>
					<template #delete-button="{ close, disabled }">
						<NcButton type="warning"
							:disabled="disabled || loading"
							:loading="loading"
							@click="() => deleteResource(close)">
							<template #icon>
								<NcLoadingIcon v-if="loading" />
								<Delete v-else :size="20" />
							</template>
							{{ deleteResourceText }}
						</NcButton>
					</template>
				</ConfirmDeleteDialog>
			</div>
		</Section>
		<Section v-if="subfoldersEnabled">
			<template #header>
				<HeaderButtonGroup :text="t('organization_folders', 'Sub-Resources')">
					<CreateResourceButton v-if="!resourcePermissionsLimited" @create="createSubResource" />
				</HeaderButtonGroup>
			</template>
			<ResourceList :resources="resource?.subResources" @click:resource="subResourceClicked" />
		</Section>
		<SectionCollapseable v-if="subfoldersEnabled && !resourcePermissionsLimited && (resource.unmanagedSubfolders.length > 0)">
			<template #header>
				<SectionHeader :text="t('organization_folders', 'Unmanaged Subfolders')"></SectionHeader>
			</template>
			<UnmanagedSubfoldersList :resource="resource" :unmanaged-subfolders="resource.unmanagedSubfolders" @promote-subfolder="promoteUnmanagedSubfolder" />
		</SectionCollapseable>
    </ModalView>
</template>

<style lang="scss" scoped>
.name-input-group {
	display: flex;
	align-items: flex-end;
	max-width: 500px;
}

.settings-group {
	display: flex;
	margin-top: 5px;
}

.settings-group > :not(:last-child) {
	margin-right: 20px;
}

.resource-active-button {
	::v-deep .checkbox-radio-switch__label {
		/* Add primary background color like other buttons */
		background-color: var(--color-primary-light);
	}
}

label {
	display: block;
}
</style>
