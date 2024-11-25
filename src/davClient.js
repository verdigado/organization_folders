
const DAV_VERDIGADO_NAMESPACE = "{http://verdigado.com/ns}";

const DAV_PROPERTIES = {
    ORGANIZATION_FOLDER_ID_PROPERTYNAME: DAV_VERDIGADO_NAMESPACE + "organization-folder-id",
	ORGANIZATION_FOLDER_UPDATE_PERMISSIONS_PROPERTYNAME: "{http://verdigado.com/ns}organization-folder-user-has-update-permissions",
	ORGANIZATION_FOLDER_RESOURCE_ID_PROPERTYNAME: DAV_VERDIGADO_NAMESPACE + "organization-folder-resource-id",
	ORGANIZATION_FOLDER_RESOURCE_UPDATE_PERMISSIONS_PROPERTYNAME: DAV_VERDIGADO_NAMESPACE + "organization-folder-resource-user-has-update-permissions",
};

/**
 * @member {OC.Files.Client} client
 */
let client

/**
 *
 * @param {OC.Files.Client} filesClient files dav client
 */
export function initFilesClient(filesClient) {
	client = filesClient
	client.addFileInfoParser((response) => {
		const data = {};
		const props = response.propStat[0].properties;

		const organizationFolderId = props[DAV_PROPERTIES.ORGANIZATION_FOLDER_ID_PROPERTYNAME];
		if (typeof organizationFolderId !== 'undefined') {
			data.organizationFolderId = parseInt(organizationFolderId);
		}

		const organizationFolderUpdatePermissions = props[DAV_PROPERTIES.ORGANIZATION_FOLDER_UPDATE_PERMISSIONS_PROPERTYNAME];
		if (typeof organizationFolderUpdatePermissions !== 'undefined') {
			data.organizationFolderUpdatePermissions = organizationFolderUpdatePermissions === "true";
		}

		const organizationFolderResourceId = props[DAV_PROPERTIES.ORGANIZATION_FOLDER_RESOURCE_ID_PROPERTYNAME];
		if (typeof organizationFolderResourceId !== 'undefined') {
			data.organizationFolderResourceId = parseInt(organizationFolderResourceId);
		}

		const organizationFolderResourceUpdatePermissions = props[DAV_PROPERTIES.ORGANIZATION_FOLDER_RESOURCE_UPDATE_PERMISSIONS_PROPERTYNAME];
		if (typeof organizationFolderResourceUpdatePermissions !== 'undefined') {
			data.organizationFolderResourceUpdatePermissions = organizationFolderResourceUpdatePermissions === "true";
		}

		return data
	});
}

(function(OC) {
	Object.assign(OC.Files.Client, DAV_PROPERTIES)
})(window.OC)

export async function getFolderProperties(path) {
	return client.getFileInfo(path, {
		properties: [
			DAV_PROPERTIES.ORGANIZATION_FOLDER_ID_PROPERTYNAME,
			DAV_PROPERTIES.ORGANIZATION_FOLDER_UPDATE_PERMISSIONS_PROPERTYNAME,
			DAV_PROPERTIES.ORGANIZATION_FOLDER_RESOURCE_ID_PROPERTYNAME,
			DAV_PROPERTIES.ORGANIZATION_FOLDER_RESOURCE_UPDATE_PERMISSIONS_PROPERTYNAME
		],
	}).then((status, fileInfo) => {
		return {status, fileInfo};
	});
}
