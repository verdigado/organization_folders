<script setup>
import { ref, watch, computed } from "vue";
import { loadState } from '@nextcloud/initial-state';
import { NcLoadingIcon, NcCheckboxRadioSwitch, NcButton, NcTextField } from '@nextcloud/vue';

import BackupRestore from "vue-material-design-icons/BackupRestore.vue";
import Delete from "vue-material-design-icons/Delete.vue";

import ResourceMembersList from "../components/MemberList/ResourceMembersList.vue";
import Permissions from "../components/Permissions/index.js";
import ConfirmDeleteDialog from "../components/ConfirmDeleteDialog.vue";
import ModalView from '../ModalView.vue';
import api from "../api.js";
import { validResourceName } from "../helpers/validation.js";

const props = defineProps({
  resourceId: {
    type: Number,
    required: true,
  },
});

const resource = ref(null);
const loading = ref(false);
const resourceActiveLoading = ref(false);

const currentResourceName = ref(false);

const resourceNameValid = computed(() => {
    return validResourceName(currentResourceName.value); 
});

const saveName = async () => {
    resource.value = await api.updateResource(resource.value.id, { name: currentResourceName.value }, "model+members");
};

const saveInheritManagers = async (inheritManagers) => {
    resource.value = await api.updateResource(resource.value.id, { inheritManagers }, "model+members");
};

watch(() => props.resourceId, async (newResourceId) => {
    loading.value = true;
    resource.value = await api.getResource(newResourceId, "model+members+subresources");
    currentResourceName.value = resource.value.name;
    loading.value = false;
}, { immediate: true });

const saveActive = async (active) => {
    resourceActiveLoading.value = true;
    resource.value = await api.updateResource(resource.value.id, { active }, "model+members");
    resourceActiveLoading.value = false;
};

const savePermission = async ({ field, value }) => {
    resource.value = await api.updateResource(resource.value.id, {
	  [field]: value,
	}, "model+members");
};

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

const organizationProviders = ref([]);

api.getOrganizationProviders().then((providers) => {
	organizationProviders.value = providers;
});

const validResourceMemberPrincipalTypes = api.PrincipalTypes;

</script>

<template>
    <ModalView :has-back-button="true" :has-next-step-button="false" :has-last-step-button="false" :title="'Resource Settings'" :loading="loading" v-slot="">
        <h3>Eigenschaften</h3>
		<div class="resource-general-settings">
			<NcTextField :value.sync="currentResourceName"
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
			<NcCheckboxRadioSwitch :checked="resource.inheritManagers" @update:checked="saveInheritManagers">Manager aus oberer Ebene vererben</NcCheckboxRadioSwitch>
		</div>
		<h3>Berechtigungen</h3>
		<Permissions :resource="resource" @permissionUpdated="savePermission" />
		<ResourceMembersList :resource-id="resource.id"
			:members="resource?.members"
			:organizationProviders="organizationProviders"
			@add-member="addMember"
			@update-member="updateMember"
			@delete-member="deleteMember"/>
		<h3>Einstellungen</h3>
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
					Resource aktiv
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
						Gruppe löschen
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
						Gruppe löschen
					</NcButton>
				</template>
			</ConfirmDeleteDialog>
		</div>
    </ModalView>
</template>

<style scoped>
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

.resource-active-button >>> .checkbox-radio-switch__label {
	/* Add primary background color like other buttons */
	background-color: var(--color-primary-light);
}

label {
	display: block;
}

h3 {
	font-weight: bold;
	margin-top: 24px;
	margin-bottom: 0;
}
</style>