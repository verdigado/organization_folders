import Vue from "vue";
import { Header } from '@nextcloud/files';
import { createPinia } from "pinia";
import { subscribe } from '@nextcloud/event-bus';

import router from "./router.js";
import HeaderComponent from "./Header.vue";

import { useCurrentDirStore } from "./stores/current-dir.js";

let vm = null;
let currentFolderFileid = null;

const pinia = createPinia();

const OrganizationFoldersHeader = new Header({
	id: 'organization_folders',
	order: 2,

	enabled(_, view) {
		return view.id === 'files' || view.id === 'folders' || view.id === 'favorites';
	},

	async render(el, folder, view) {
		if(!vm) {
			el.id = "organization_folders";
			vm = new Vue({
				el,
                router,
                pinia,
				render: h => h(HeaderComponent),
			});
		} else {
			// the outer vue instance calling will have replaced el, so we need to "re-mount" our vue instance
			el.replaceWith(vm.$el);
		}

        const currentDir = useCurrentDirStore();
        currentDir.update(folder?.path, folder?.displayname, folder.attributes);
        currentFolderFileid = folder?.fileid;
	},

	updated(folder) {
		const currentDir = useCurrentDirStore();
        currentDir.update(folder?.path, folder?.displayname, folder.attributes);
        currentFolderFileid = folder?.fileid;
	},
})

// Handle empty folders seperately, because Headers are not rendered in this case :/
subscribe("files:list:updated", ({view, folder, contents}) => {
	if(contents.length === 0) {
		// only re-render, if open folder has changed
		if(folder && currentFolderFileid !== folder.fileid) {
			const fileListHeader = document.querySelector(".app-files .files-list__header");

			const vueContainer = document.createElement("div");
			vueContainer.style.width = "100%";

			console.log("vueContainer.nextElementSibling", fileListHeader.nextElementSibling);

			fileListHeader.parentNode.insertBefore(vueContainer, fileListHeader.nextElementSibling);

			OrganizationFoldersHeader.render(vueContainer, folder, view);
		}
	}
});

export default OrganizationFoldersHeader;