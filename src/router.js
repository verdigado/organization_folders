import Vue from "vue";
import Router from "vue-router";

import ResourceSettings from "./views/ResourceSettings.vue";

Vue.use(Router);

const router = new Router({
	mode: 'abstract',
	routes: [
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