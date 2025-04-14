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

import BackupRestore from "vue-material-design-icons/BackupRestore.vue";
import Delete from "vue-material-design-icons/Delete.vue";

import HeaderButtonGroup from "../components/SectionHeaderButtonGroup.vue";
import Section from "../components/Section.vue";
import SectionHeader from "../components/SectionHeader.vue";
import MembersList from "../components/MemberList/MembersList.vue";
import Permissions from "../components/Permissions/index.js";
import ConfirmDeleteDialog from "../components/ConfirmDeleteDialog.vue";
import ResourceList from "../components/ResourceList.vue";
import CreateResourceButton from "../components/CreateResourceButton.vue";
import CreateMemberButton from "../components/CreateMemberButton/CreateMemberButton.vue";

import ModalView from '../ModalView.vue';

import api from "../api.js";
import { useOrganizationProvidersStore } from "../stores/organization-providers.js";
import { validResourceName } from "../helpers/validation.js";

const props = defineProps({
  resourceId: {
    type: Number,
    required: true,
  },
});

const organizationProviders = useOrganizationProvidersStore();

organizationProviders.initialize();

const resource = ref(null);
const loading = ref(false);
const resourceActiveLoading = ref(false);

const memberPermissionLevelOptions = [
  { label: "Mitglied", value: 1 },
  { label: "Manager", value: 2 },
];

const currentResourceName = ref(false);

const resourceNameValid = computed(() => {
    return validResourceName(currentResourceName.value); 
});

const saveName = async () => {
    resource.value = await api.updateResource(resource.value.id, { name: currentResourceName.value }, "model+members+subresources");
};

const saveInheritManagers = async (inheritManagers) => {
    resource.value = await api.updateResource(resource.value.id, { inheritManagers }, "model+members+subresources");
};

const resourcePermissionsLimited = computed(() => {
	return resource.value?.permissions?.level === "limited"; 
});

watch(() => props.resourceId, async (newResourceId) => {
    loading.value = true;
    resource.value = await api.getResource(newResourceId, "model+permissions+members+subresources");
    currentResourceName.value = resource.value.name;
    loading.value = false;
}, { immediate: true });

const saveActive = async (active) => {
    resourceActiveLoading.value = true;
    resource.value = await api.updateResource(resource.value.id, { active }, "model+members+subresources");
    resourceActiveLoading.value = false;
};

const savePermission = async ({ field, value }) => {
    resource.value = await api.updateResource(resource.value.id, {
	  [field]: value,
	}, "model+members+subresources");
};

const deleteResource = async (closeDialog) => {
	await api.deleteResource(resource.value.id);
	closeDialog();
	backButtonClicked();
}

const switchToSnapshotRestoreView = ()  => {

};

const addMember = async (principalType, principalId) => {
	resource.value.members.push(await api.createResourceMember(resource.value.id, {
		permissionLevel: api.ResourceMemberPermissionLevels.MEMBER,
		principalType,
		principalId,
	}));
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

const router = useRouter();

const subResourceClicked = (resource) => {
	router.push({
		path: '/resource/' + resource.id,
	});
};

const backButtonClicked = () => {
	if(resource.value?.parentResource) {
		router.push({
			path: '/resource/' + resource.value.parentResource,
		});
	} else {
		router.push({
			path: '/organizationFolder/' + resource.value.organizationFolderId
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

const title = computed(() =>{
	if(resource.value?.type === api.ResourceTypes.FOLDER) {
		return t("organization_folders", "Folder Settings");
	} else {
		return t("organization_folders", "Settings");
	}
});

const permissionLevelExplanation = computed(() => {
	if(resource.value?.type === api.ResourceTypes.FOLDER) {
		return t("organization_folders", "Managers have access to the settings of this folder");
	} else {
		return "";
	}
});

</script>

<template>
    <ModalView
		:has-back-button="true"
		:has-next-step-button="false"
		:has-last-step-button="false"
		:title="title"
		:loading="loading"
		v-slot=""
		@back-button-pressed="backButtonClicked">
		<NcNoteCard v-if="resourcePermissionsLimited"
			type="info"
			text="Du hast nicht die Berechtigung diesen Ordner zu verwalten" />
		<Section>
			<template #header>
				<SectionHeader text="Eigenschaften"></SectionHeader>
			</template>
			<NcTextField :value.sync="currentResourceName"
				:disabled="resourcePermissionsLimited"
				:class="{ 'not-allowed-cursor': resourcePermissionsLimited }"
				:error="!resourceNameValid"
				:label-visible="!resourceNameValid"
				:label-outside="true"
				:helper-text="resourceNameValid ? '' : 'Ungültiger Name'"
				label="Name"
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
				Manager aus oberer Ebene vererben
			</NcCheckboxRadioSwitch>
		</Section>
		<Section v-if="!resourcePermissionsLimited">
			<template #header>
				<SectionHeader text="Berechtigungen"></SectionHeader>
			</template>
			<Permissions :resource="resource"
				@permissionUpdated="savePermission" />
		</Section>
		<Section v-if="!resourcePermissionsLimited">
			<template #header>
				<HeaderButtonGroup text="Members">
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
				<SectionHeader text="Einstellungen"></SectionHeader>
			</template>
			<div class="settings-group">
				<NcButton v-if="snapshotIntegrationActive" @click="switchToSnapshotRestoreView">
					<template #icon>
						<BackupRestore />
					</template>
					Aus Backup wiederherstellen
				</NcButton>
				<div class="resource-active-button">
					<NcCheckboxRadioSwitch :checked="resource.active"
						:loading="resourceActiveLoading"
						type="checkbox"
						@update:checked="saveActive">
						Ordner aktiv
					</NcCheckboxRadioSwitch>
				</div>
				<ConfirmDeleteDialog title="Ordner löschen"
					:loading="loading"
					button-label="Ordner löschen"
					:match-text="resource.name">
					<template #activator="{ open }">
						<NcButton v-tooltip="resource.active ? 'Nur deaktivierte Resourcen können gelöscht werden' : undefined"
							style="height: 52px;"
							:disabled="resource.active"
							type="error"
							@click="open">
							Ordner löschen
						</NcButton>
					</template>
					<template #content>
						<p style="margin: 1rem 0 1rem 0">
							Du bist dabei den Ordner {{ resource.name }} und den Inhalt komplett zu löschen.
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
							Ordner löschen
						</NcButton>
					</template>
				</ConfirmDeleteDialog>
			</div>
		</Section>
		<Section>
			<template #header>
				<HeaderButtonGroup text="Unter-Resourcen">
					<CreateResourceButton @create="createSubResource" />
				</HeaderButtonGroup>
			</template>
			<ResourceList :resources="resource?.subResources" @click:resource="subResourceClicked" />
		</Section>
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