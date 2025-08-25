<script setup>
import { ref, watch, computed, nextTick } from "vue";
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
import AccountEye from "vue-material-design-icons/AccountEye.vue";
import FolderMove from "vue-material-design-icons/FolderMove.vue";
import DeleteForever from "vue-material-design-icons/DeleteForever.vue";

import HeaderButtonGroup from "../components/SectionHeaderButtonGroup.vue";
import Section from "../components/Section.vue";
import SectionCollapseable from "../components/SectionCollapseable.vue";
import SectionHeader from "../components/SectionHeader.vue";
import SubSection from "../components/SubSection.vue";
import SubSectionHeader from "../components/SubSectionHeader.vue";
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
import MoveResourceDialog from "../components/MoveResourceDialog.vue";
import WouldRevokeManagementPermissionsDialog from "../components/WouldRevokeManagementPermissionsDialog.vue";
import WouldChangeManyUsersPermissionsDialog from "../components/WouldChangeManyUsersPermissionsDialog.vue";
import EditCancelSaveButtons from "../components/EditCancelSaveButtons.vue";

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

const organizationFolder = ref(null);
const resource = ref(null);
const resourceLoading = ref(false);
const organizationFolderLoading = ref(false);
const inheritManagersLoading = ref(false);
const resourceActiveLoading = ref(false);

const nameEditActive = ref(false);
const saveNameLoading = ref(false);

const revokeOwnManagementPermissionsDialogOpen = ref(false);
let revokeOwnManagementPermissionsDialogRetryApiRequest = null;
let revokeOwnManagementPermissionsDialogCancelApiRequest = null;

const tooManyPermissionsChangesDialogOpen = ref(false);
const tooManyPermissionsChangesDialogDetails = ref({});
let tooManyPermissionsChangesDialogRetryApiRequest = null;
let tooManyPermissionsChangesDialogCancelApiRequest = null;

const permissionsReportOpen = ref(false);
const permissionsReportLoading = ref(true);
const permissionsReportPage = ref("overview");

const permissionsReport = ref(null);
const userPermissionsReport = ref(null);

const currentResourceName = ref(false);

const moveDialogOpen = ref(false);

const loading = computed(() => {
    return resourceLoading.value || organizationFolderLoading.value;
});

const resourceNameValid = computed(() => {
    return validResourceName(currentResourceName.value);
});

const nameTextField = ref(null);

const editName = () => {
	currentResourceName.value = resource.value.name;
	nameEditActive.value = true;
	nextTick(() => {
		nameTextField.value?.focus();
	});
};

const saveName = async () => {
	saveNameLoading.value = true;
	try {
		resource.value = await api.moveResource(resource.value.id, {
			name: currentResourceName.value,
			parentResourceId: resource.value.parentResource,
		}, resourceApiIncludes);
	} finally {
		saveNameLoading.value = false;
		nameEditActive.value = false;
	}
};

const cancelNameEdit = () => {
	nameEditActive.value = false;
};

const move = async (newParentResourceId, callback) => {
	try {
		resource.value = await api.moveResource(resource.value.id, {
			name: resource.value.name,
			parentResourceId: newParentResourceId,
		}, resourceApiIncludes);
		// TODO: It would be great to also move the users filebrowser behind modal to
		// new location in filesystem if moved resource was open
	} finally {
		callback();
	}
};

const saveInheritManagers = (inheritManagers) => {
	inheritManagersLoading.value = true;
	api.updateResource(resource.value.id, { inheritManagers }, resourceApiIncludes)
		.then((updatedResource) => {
			resource.value = updatedResource;
			inheritManagersLoading.value = false;
		})
		.catch((error) => {
			if(error.response?.data?.id === "WouldRevokeUsersManagementPermissions") {
				revokeOwnManagementPermissionsDialogRetryApiRequest = async () => {
					resource.value = await api.updateResource(resource.value.id, { inheritManagers }, resourceApiIncludes, false);
					inheritManagersLoading.value = false;
				};
				revokeOwnManagementPermissionsDialogCancelApiRequest = () => {
					inheritManagersLoading.value = false;
				};
				revokeOwnManagementPermissionsDialogOpen.value = true;
			}
		});
};

const revokeOwnManagementPermissionsDialogCancel = () => {
	revokeOwnManagementPermissionsDialogOpen.value = false;
	if(revokeOwnManagementPermissionsDialogCancelApiRequest) {
		revokeOwnManagementPermissionsDialogCancelApiRequest();
	}
}

const revokeOwnManagementPermissionsDialogContinue = async (callback) => {
	if(revokeOwnManagementPermissionsDialogRetryApiRequest) {
		await revokeOwnManagementPermissionsDialogRetryApiRequest();
	}
	revokeOwnManagementPermissionsDialogRetryApiRequest = null;
	callback();
	revokeOwnManagementPermissionsDialogOpen.value = false;
	backButtonClicked();
}

const tooManyPermissionsChangesDialogCancel = () => {
	tooManyPermissionsChangesDialogOpen.value = false;
	if(tooManyPermissionsChangesDialogCancelApiRequest) {
		tooManyPermissionsChangesDialogCancelApiRequest();
	}
	tooManyPermissionsChangesDialogCancelApiRequest = null;
}

const tooManyPermissionsChangesDialogContinue = async (callback) => {
	if(tooManyPermissionsChangesDialogRetryApiRequest) {
		await tooManyPermissionsChangesDialogRetryApiRequest();
	}
	tooManyPermissionsChangesDialogRetryApiRequest = null;
	callback();
	tooManyPermissionsChangesDialogOpen.value = false;
}

const resourcePermissionsLimited = computed(() => {
    return resource.value?.permissions?.level === "limited";
});

watch(() => props.resourceId, async (newResourceId) => {
    resourceLoading.value = true;
	resource.value = await api.getResource(newResourceId, resourceApiIncludes);
    currentResourceName.value = resource.value.name;
	permissionsReport.value = undefined;
	userPermissionsReport.value = undefined;
    resourceLoading.value = false;
}, { immediate: true });

watch(() => props.organizationFolderId, async (newOrganizationFolderId) => {
    organizationFolderLoading.value = true;
	organizationFolder.value = await api.getOrganizationFolder(newOrganizationFolderId, "model")
    organizationFolderLoading.value = false;
}, { immediate: true });

const saveActive = async (active) => {
    resourceActiveLoading.value = true;
	try {
		resource.value = await api.updateResource(resource.value.id, { active }, resourceApiIncludes, false, null);
	} finally {
		resourceActiveLoading.value = false;
	}
};

const savePermission = async ({ field, value, callback }) => {
    api.updateResource(resource.value.id, {
	  [field]: value,
	}, resourceApiIncludes, false).then((updatedResource) => {
		resource.value = updatedResource;
		callback();
	})
	.catch((error) => {
		if(error.response?.data?.id === "WouldCauseTooManyPermissionsChanges") {
			tooManyPermissionsChangesDialogRetryApiRequest = async () => {
				resource.value = await api.updateResource(resource.value.id, { [field]: value }, resourceApiIncludes, false, null);
				callback();
			};
			tooManyPermissionsChangesDialogCancelApiRequest = () => {
				callback();
			};
			tooManyPermissionsChangesDialogDetails.value = error.response?.data?.details;
			tooManyPermissionsChangesDialogOpen.value = true;
		} else {
			callback();
		}
	});
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

const updateMember = async (memberId, updateResourceMemberDto, callback) => {
	api.updateResourceMember(memberId, updateResourceMemberDto)
		.then((member) => {
			resource.value.members = resource.value.members.map((m) => m.id === member.id ? member : m);
			callback();
		})
		.catch((error) => {
			if(error.response?.data?.id === "WouldRevokeUsersManagementPermissions") {
				revokeOwnManagementPermissionsDialogRetryApiRequest = async () => {
					let member = await api.updateResourceMember(memberId, updateResourceMemberDto, false);
					callback();
					resource.value.members = resource.value.members.map((m) => m.id === member.id ? member : m);
				};
				revokeOwnManagementPermissionsDialogCancelApiRequest = () => {
					callback();
				};
				revokeOwnManagementPermissionsDialogOpen.value = true;
			} else {
				callback();
			}
		});
};

const deleteMember = (memberId, callback) => {
	api.deleteResourceMember(memberId)
		.then(() => {
			resource.value.members = resource.value.members.filter((m) => m.id !== memberId);
			callback();
		})
		.catch((error) => {
			if(error.response?.data?.id === "WouldRevokeUsersManagementPermissions") {
				revokeOwnManagementPermissionsDialogRetryApiRequest = async () => {
					await api.deleteResourceMember(memberId, false);
					callback();
					resource.value.members = resource.value.members.filter((m) => m.id !== memberId);
				};
				revokeOwnManagementPermissionsDialogCancelApiRequest = () => {
					callback();
				};
				revokeOwnManagementPermissionsDialogOpen.value = true;
			} else {
				callback();
			}
		});
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
	if(principalId) {
		permissionsReportLoading.value = true;
		userPermissionsReport.value = await api.getResourceUserPermissionsReport(resource.value.id, principalId);
		permissionsReportLoading.value = false;
	} else {
		userPermissionsReport.value = null;
	}
};

const openMoveDialog = () => {
	moveDialogOpen.value = true;
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
				<SectionHeader :text="t('organization_folders', 'Settings')"></SectionHeader>
			</template>
			<SubSection>
				<template #header>
					<SubSectionHeader :text="t('organization_folders', 'Name')" />
				</template>
				<div style="display: flex; flex-direction: row; align-items: center; column-gap: 3px;">
					<p v-if="!nameEditActive" style="padding-left: 10px;">{{ resource.name }}</p>
					<NcTextField v-else
						ref="nameTextField"
						:value.sync="currentResourceName"
						:error="!resourceNameValid"
						:helper-text="resourceNameValid ? '' : t('organization_folders', 'Invalid name')"
						:label="t('organization_folders', 'Name')"
						:label-outside="true"
						style="--color-border-maxcontrast: #949494;"
						@trailing-button-click="saveName"
						@blur="() => currentResourceName = currentResourceName.trim()"
						@keyup.enter="saveName"
						@keydown.esc.stop.prevent
						@keyup.esc.stop.prevent="cancelNameEdit" />
					<EditCancelSaveButtons v-if="!resourcePermissionsLimited"
						:edit-active="nameEditActive"
						:loading="saveNameLoading"
						@edit="editName"
						@cancel="cancelNameEdit"
						@save="saveName" />
				</div>
			</SubSection>

			<SubSection>
				<template #header>
					<SubSectionHeader :text="t('organization_folders', 'Inheritance')" />
				</template>
				<NcCheckboxRadioSwitch
					:checked="resource.inheritManagers"
					:disabled="resourcePermissionsLimited"
					:loading="inheritManagersLoading"
					:class="{ 'not-allowed-cursor': resourcePermissionsLimited }"
					style="margin-top: 12px; padding-left: 10px;"
					@update:checked="saveInheritManagers">
					{{ t("organization_folders", "Inherit managers from the level above") }}
				</NcCheckboxRadioSwitch>
			</SubSection>

			<WouldRevokeManagementPermissionsDialog
				:resource="resource"
				:open="revokeOwnManagementPermissionsDialogOpen"
				@cancel="revokeOwnManagementPermissionsDialogCancel"
				@continue="revokeOwnManagementPermissionsDialogContinue" />
			<WouldChangeManyUsersPermissionsDialog
				:resource="resource"
				:open="tooManyPermissionsChangesDialogOpen"
				:added="tooManyPermissionsChangesDialogDetails?.numberOfUsersWithPermissionsAdded"
				:removed="tooManyPermissionsChangesDialogDetails?.numberOfUsersWithPermissionsDeleted"
				@cancel="tooManyPermissionsChangesDialogCancel"
				@continue="tooManyPermissionsChangesDialogContinue" />
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
						:initial-role-organization-path="organizationFolder?.organizationFullHierarchy ?? []"
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
			<div class="button-group">
				<NcButton @click="openPermissionsReport">
					{{ t("organization_folders", "Show Permissions Overview") }}
					<template #icon>
						<AccountEye :size="20" />
					</template>
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
							{{ t("organization_folders", "Permissions Overview") }}
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
				<NcButton @click="openMoveDialog">
					{{ t("organization_folders", "Move folder") }}
					<template #icon>
						<FolderMove :size="20" />
					</template>
				</NcButton>
				<MoveResourceDialog
					:organization-folder="organizationFolder"
					:resource="resource"
					:open="moveDialogOpen"
					@update:open="(newValue) => moveDialogOpen = newValue"
					@move="move" />
				<NcButton v-if="snapshotIntegrationActive" @click="switchToSnapshotRestoreView">
					<template #icon>
						<BackupRestore />
					</template>
					{{ t("organization_folders", "Restore files from a backup") }}
				</NcButton>
				<NcCheckboxRadioSwitch :checked="resource.active"
					:loading="resourceActiveLoading"
					type="checkbox"
					@update:checked="saveActive">
					{{ t("organization_folders", "Resource active") }}
				</NcCheckboxRadioSwitch>
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
							<template #icon>
								<DeleteForever />
							</template>
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

.button-group {
	margin-top: 5px;
	display: flex;
	flex-wrap: wrap;
	column-gap: 20px;
	row-gap: 10px;
	justify-content: flex-start;

	> * {
		height: 55px;
		white-space: nowrap;
		box-sizing: border-box;

		:deep(.checkbox-radio-switch__content) {
			--default-clickable-area: 55px;
			/* Add primary background color like other buttons */
			background-color: var(--color-primary-light);
		}
	}
}

label {
	display: block;
}
</style>
