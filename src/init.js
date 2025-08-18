import { registerDavProperty } from "@nextcloud/files/dav";

const DAV_VERDIGADO_NAMESPACE = { verdigado: "http://verdigado.com/ns" };

const DAV_PROPERTIES = [
    "organization-folder-id",
	"organization-folder-user-has-update-permissions",
	"organization-folder-user-has-read-limited-permissions",
	"organization-folder-resource-id",
	"organization-folder-resource-user-has-update-permissions",
];

for(let property of DAV_PROPERTIES) {
	registerDavProperty("verdigado:" + property, DAV_VERDIGADO_NAMESPACE);
}
