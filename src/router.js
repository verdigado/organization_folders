import Vue from "vue";
import Router from "vue-router";

import OrganizationFolders from "./views/OrganizationFolders.vue";
import OrganizationFolderSettings from "./views/OrganizationFolderSettings.vue";
import ResourceSettings from "./views/ResourceSettings.vue";

Vue.use(Router);

const router = new Router({
	mode: 'abstract',
	routes: [
		{
			path: "/organizationFolders",
			name: "organizationFolders",
			component: OrganizationFolders,
		},
		{
			path: "/organizationFolder/:organizationFolderId",
			name: "organizationFolder-settings",
			component: OrganizationFolderSettings,
			props: (route) => (
				{
					organizationFolderId: Number.parseInt(route.params.organizationFolderId, 10) || undefined,
				}
			),
		},
		{
			path: "/resource/:resourceId",
			name: "resource-settings",
			component: ResourceSettings,
			props: (route) => (
				{
					resourceId: Number.parseInt(route.params.resourceId, 10) || undefined,
				}
			),
		},
	],
});

export default router;