<script setup>
import { ref, watch, set, computed } from "vue";

import { useRouter } from "vue2-helpers/vue-router";

import BackupRestore from "vue-material-design-icons/BackupRestore.vue";

import NcEmptyContent from "@nextcloud/vue/components/NcEmptyContent";
import NcListItem from "@nextcloud/vue/components/NcListItem";
import NcProgressBar from "@nextcloud/vue/components/NcProgressBar";
import NcLoadingIcon from "@nextcloud/vue/components/NcLoadingIcon";

import ModalView from '../ModalView.vue';
import Versions from "../components/Illustrations/Versions.vue";
import FileSearch from "../components/Illustrations/FileSearch.vue";
import ListDiffItem from '../components/ListDiffItem.vue';

import api from "../api.js";

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

const loading = ref(true);
const error = ref(false);
const errorMessage = ref(false);

const router = useRouter();

const snapshots = ref([]);

const selectedSnapshot = ref(false);

let selectedSnapshotDiff = ref({});

let selectedSnapshotDiffRequest = ref(false);

let selectedDiffItemIds = ref([]);

const step = ref(0);

const step2Progress = ref(0);
const step4Progress = ref(0);

const selectedDiffItems = computed(() => {
	if(selectedDiffItemIds.value) {
		return selectedSnapshotDiff.value?.results?.filter((diffItem) => (selectedDiffItemIds.value.includes(diffItem.id))) || [];
	} else {
		return [];
	}
})

const parentDirectory = computed(() => {
	console.log(selectedSnapshotDiff.value);
	if(step.value >= 2 && !loading.value) {
		return selectedSnapshotDiff.value?.relativePath;
	} else {
		return "";
	}
})

function selectDiffItem(diffId, newValue) {
	if(!selectedDiffItemIds.value) {
		set(view.context, "selectedDiffItemIds", [])
	}

	if(newValue) {
		if(!selectedDiffItemIds.value.includes(diffId)) {
			selectedDiffItemIds.value.push(diffId)
		}
	} else {
		const index = selectedDiffItemIds.value.indexOf(diffId)

		if (index > -1) {
			selectedDiffItemIds.value.splice(index, 1);
		}
	}
}

async function loadSnapshots() {
    loading.value = true;
    snapshots.value = await api.getResourceSnapshots(props.resourceId);
    loading.value = false;
}

async function loadDiff() {
    step2Progress.value = 0;
    loading.value = true;

    selectedSnapshotDiffRequest.value = api.createResourceSnapshotDiff(props.resourceId, selectedSnapshot.value, (event) => {
        if(event.status == "error") {
            error.value = true;
            errorMessage.value = event.errorMessage;
        } else {
            if(event.result) {
                selectedSnapshotDiff.value = event.result;
            }
			if(event.progress) {
                step2Progress.value = event.progress * 100;
            }
        }
    });
}

async function revertSelectedDiffTaskResults() {
	step4Progress.value = 0;
    loading.value = true;

	const numberOfSelectedDiffTaskResults = selectedDiffItems.value.length;

	console.log(numberOfSelectedDiffTaskResults);

	for (const [index, diffTaskResult] of selectedDiffItems.value.entries()) {
		await api.revertResourceSnapshotDiffItem(props.resourceId, selectedSnapshot.value, selectedSnapshotDiff?.value?.id, diffTaskResult.id);
		step4Progress.value = Math.round(((index + 1) / numberOfSelectedDiffTaskResults) * 100)
		console.log(step4Progress.value);
	}

	setTimeout(() => {
        loading.value = false;
    }, 1000);
}


watch(()=>step.value, async (newStep, oldStep) => {
    if(oldStep < newStep) {
        if(newStep === 2) {
            loadDiff();
        } else if(newStep === 4) {
			revertSelectedDiffTaskResults();
		} else if(newStep > 4) {
			returnToResourceView();
		}
    } else {
        abortXHRRequest(selectedSnapshotDiffRequest.value);
    }
});

watch(step2Progress, async (newProgress) => {
    if(newProgress >= 100) {
        setTimeout(() => {
            loading.value = false;
        }, 1000);
    } else {
        loading.value = true;
    }
}, { immediate: true });

function abortXHRRequest(request) {
    if(request?.abort) {
        request.abort();
    }
}

watch(selectedSnapshotDiffRequest, (newRequest, oldRequest) => {
    abortXHRRequest(oldRequest);
});

function diffItemRevertDescription(diffItem) {
	switch(diffItem.type) {
		case "CREATION":
			return t("organization_folders", "{filePath} will be deleted", { filePath: diffItem?.current?.path })
		case "EDIT":
			return t("organization_folders", "The changes in {filePath} will be reverted", { filePath: diffItem?.current?.path })
		case "RENAME":
			return t("organization_folders", "{oldFilePath} will be renamed to {newFilePath}", { oldFilePath: diffItem?.current?.path, newFilePath: diffItem?.before?.path })
		case "DELETION":
			return t("organization_folders", "{filePath} will be restored", { filePath: diffItem?.before?.path })
		default:
			return "";
	}
}

const returnToResourceView = () => {
	router.push({
		path: '/organizationFolder/' + props.organizationFolderId + '/resource/' + props.resourceId,
	});
};

loadSnapshots();

</script>

<template>
	<ModalView
		:has-back-button="true"
		:has-next-step-button="true"
		:next-step-button-enabled="!loading && !error && ((step === 0) || (step === 1 && !!selectedSnapshot) || (step === 2 && selectedDiffItemIds.length > 0) || (step > 2))"
		:has-previous-step-button="step > 0"
		:previous-step-button-enabled="!loading && step > 0 && step < 4"
		:next-step-button-text="step < 4 ? t('organization_folders', 'Next') : t('organization_folders', 'Finish')"
		:title="t('organization_folders', 'Snapshot Restore Wizard')"
		:loading="loading && step !== 0 && step !== 2 && step !== 4"
		v-slot=""
		@next-step-button-pressed="step++"
		@previous-step-button-pressed="step--"
		@back-button-pressed="returnToResourceView">
		<div class="ignoreForLayout restore">
			<div v-if="step === 0" class="ignoreForLayout">
				<div class="illustration flex_grow">
					<Versions/>
				</div>
				<p class="instruction" v-html="t('organization_folders', 'Accidentally deleted a file? Need an older version of a file?<br>No worries—you can restore it from the backups.<br>To select from which backup you want to restore, click Next.', { escape: false })"></p>
			</div>
			<div v-if="step === 1" class="ignoreForLayout">
				<p class="instruction">{{ t("organization_folders", "From which backup do you want to restore?") }}</p>
				<div class="flex_grow restore_loading" v-if="loading">
					<NcLoadingIcon :size="50" />
				</div>
				<div class="snapshots" v-if="!loading">
					<NcEmptyContent v-if="snapshots.length === 0" :name="t('organization_folders', 'No backups containing this folder exist yet.')">
						<template #icon>
							<BackupRestore />
						</template>
					</NcEmptyContent>
					<ul v-else>
						<NcListItem v-for="snapshot in snapshots"
							:key="snapshot.id"
							class="material_you listItemSelectable"
							:class="{
								'selected': (selectedSnapshot === snapshot.id),
							}"
							:name="snapshot?.name"
							:force-display-actions="true"
							@click="() => {selectedSnapshot = snapshot.id}">
							<template #icon>
								<BackupRestore :size="44" />
							</template>
							<template #subname>
								{{ snapshot?.createdTimestamp?.toLocaleString() }}
							</template>
							<template #actions>
								actions
							</template>
						</NcListItem>
					</ul>
				</div>
				<div class="flex_grow" v-if="!loading"></div>
			</div>
			<div v-if="step === 2" class="ignoreForLayout">
				<div class="ignoreForLayout" v-if="loading">
					<p class="instruction">{{ t("organization_folders", "Calculating changes since the selected backup...") }}</p>
					<div class="illustration flex_grow">
						<FileSearch :progress="step2Progress" :error="error"/>
					</div>
					<NcProgressBar class="progressBar" :value="step2Progress" size="medium" />
					<p class="instruction" v-if="!error">{{ step2Progress + "%" }}</p>
					<p class="instruction" v-if="error">{{ t("organization_folders", "An error occurred: {errorMessage}", { errorMessage }) }}</p>
				</div>
				<div class="ignoreForLayout" v-if="!loading">
					<p class="instruction" v-html="t('organization_folders', 'The files below have changed since the selected backup.<br>Pick the ones you want to restore to the version in the backup.', { escape: false })"></p>
					<ul v-if="selectedSnapshotDiff?.results?.length > 0">
						<ListDiffItem v-for="diff in selectedSnapshotDiff.results"
							:key="diff?.before?.path || diff?.current?.path"
							class="material_you"
							:selected="selectedDiffItemIds && selectedDiffItemIds.includes(diff.id)"
							:diff="diff"
							:parentDirectory="parentDirectory"
							@update:selected="(newValue) => selectDiffItem(diff.id, newValue)">
						</ListDiffItem>
					</ul>
					<div v-else>
						<NcEmptyContent :name="t('organization_folders', 'No changes')"
							:description="t('organization_folders', 'No files have changed since the backup.')">
							<template #icon>
								<BackupRestore />
							</template>
						</NcEmptyContent>
					</div>
					<div class="flex_grow"></div>
				</div>
			</div>
			<div v-if="step === 3" class="ignoreForLayout">
				<p class="instruction">{{ t("organization_folders", "Review and confirm the actions listed below.") }}</p>
				<ul class="confirmRevertDiffItemList">
					<li v-for="diff in selectedDiffItems"
						:key="diff.id">
						{{ diffItemRevertDescription(diff) }}
					</li>
				</ul>
				<div class="flex_grow"></div>
			</div>
			<div v-if="step === 4" class="ignoreForLayout">
				<div class="flex_grow"></div>
				<NcProgressBar class="progressBar" :value="step4Progress" size="medium" />
				<p class="instruction">{{ step4Progress + "%" }}</p>
			</div>
		</div>
	</ModalView>
</template>

<style scoped>
.restore {
    overflow-y: scroll;
  	overflow-x: hidden;
}
.illustration {
    height: 10px; /* Random value needed, the flexbox will grow it to fit the available space*/
    margin-top: 50px;
    margin-bottom: 50px;
    display: flex;
    justify-content: center;
}

.illustration > svg, .illustration > img {
    height: 100%;
}

.instruction {
    font-size: large;
    text-align: center;
}

.restore_loading {
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.flex_grow {
    flex: 1;
}

.snapshots {
	overflow-y: scroll;
	overflow-x: hidden;

	> li {
		padding: 2px 0px !important;
	}
}

.progressBar::-webkit-progress-value {
    transition: width 1s ease-in-out;
}

.confirmRevertDiffItemList {
	list-style-position: inside;
	list-style: disc;
	margin-top: 20px;
}
</style>