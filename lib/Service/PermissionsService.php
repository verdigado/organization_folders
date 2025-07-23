<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Service;

use Exception;

use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Db\ResourceMember;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\PrincipalBackedByGroup;
use OCA\OrganizationFolders\Model\InheritedPrincipal;
use OCA\OrganizationFolders\Model\ResourcePermissionsList;
use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Manager\ACLManager;
use OCA\OrganizationFolders\Model\ResourcePermissionsListWithOriginTracing;

class PermissionsService {
	public function __construct(
		protected readonly OrganizationFolderService $organizationFolderService,
		protected readonly OrganizationFolderMemberService $organizationFolderMemberService,
		protected readonly ResourceService $resourceService,
		protected readonly ResourceMemberService $resourceMemberService,
		protected readonly ACLManager $aclManager,
	) {
	}

	private function addInheritanceOriginToPrincipal(Principal $principal, OrganizationFolder|Resource $origin): InheritedPrincipal {
		return new InheritedPrincipal($principal, $origin);
	}

	/**
	 * @param Principal[] $principals
	 * @param OrganizationFolder|Resource $origin
	 * @return InheritedPrincipal[]
	 */
	private function addInheritanceOriginToPrincipals(array $principals, OrganizationFolder|Resource $origin): array {
		$result = [];
		
		foreach($principals as $principal) {
			$result[] = new InheritedPrincipal($principal, $origin);
		}

		return $result;
	}

	/**
	 * @param Resource $resource
	 * @param InheritedPrincipal[] $inheritedMemberPrincipals
	 * @param InheritedPrincipal[] $inheritedManagerPrincipals
	 * @param ResourceMember $resourceMembers
	 * @param ResourceMember $resourceManagers
	 * @param bool $implicitlyDeactivated
	 * @return array{ permissionsList: ResourcePermissionsList, nextInheritedMemberPrincipals: InheritedPrincipal[], nextInheritedManagerPrincipals: InheritedPrincipal[], nextImplicitlyDeactivated: bool }
	 */
	private function calculateResourcePermissionsListAndFurtherInheritedPrincipals(
		Resource $resource,
		array $inheritedMemberPrincipals,
		array $inheritedManagerPrincipals,
		array $resourceMembers,
		array $resourceManagers,
		bool $implicitlyDeactivated = false,
		bool $enableOriginTracing = false,
	) {
		// calculate actual permissions and if
		// inherited principals should be forwarded down the tree
		if($resource->getActive() && !$implicitlyDeactivated) {
			$inheritedMemberPermissions = $resource->getInheritedAclPermission();

			if($inheritedMemberPermissions > 0) {
				$nextInheritedMemberPrincipals = $inheritedMemberPrincipals;
			} else {
				$nextInheritedMemberPrincipals = [];
			}

			if($resource->getInheritManagers()) {
				$inheritedManagerPermissions = $resource->getManagersAclPermission();
				$nextInheritedManagerPrincipals = $inheritedManagerPrincipals;
			} else {
				$inheritedManagerPermissions = 0;
				$nextInheritedManagerPrincipals = [];
			}

			$resourceMembersAclPermission = $resource->getMembersAclPermission();

			$resourceManagersAclPermission = $resource->getManagersAclPermission();
		} else {
			$inheritedMemberPermissions = 0;
			$nextInheritedMemberPrincipals = [];

			$inheritedManagerPermissions = 0;
			$nextInheritedManagerPrincipals = [];

			$resourceMembersAclPermission = 0;

			$resourceManagersAclPermission = 0;
		}
		
		if($enableOriginTracing) {
			$permissionsList = new ResourcePermissionsListWithOriginTracing($resource);
		} else {
			$permissionsList = new ResourcePermissionsList($resource);
		}

		// Inherited Member Permissions
		foreach($inheritedMemberPrincipals as $inheritedMemberPrincipal) {
			$permissionsList->addPermission(
				principal: $inheritedMemberPrincipal->getPrincipal(),
				permissions: $inheritedMemberPermissions,
				permissionOrigin: [
					"type" => "inherited_member_from",
					"origin" => $inheritedMemberPrincipal->getOrigin(),
				],
			);
		}

		// Inherited Manager Permissions
		foreach($inheritedManagerPrincipals as $inheritedManagerPrincipal) {
			$permissionsList->addPermission(
				principal: $inheritedManagerPrincipal->getPrincipal(),
				permissions: $inheritedManagerPermissions,
				permissionOrigin: [
					"type" => "inherited_manager_from",
					"origin" => $inheritedManagerPrincipal->getOrigin(),
				],
			);
		}

		// Member Permissions
		if($resourceMembersAclPermission > 0) {
			foreach($resourceMembers as $resourceMember) {
				$memberPrincipal = $resourceMember->getPrincipal();

				$permissionsList->addPermission(
					principal: $memberPrincipal,
					permissions: $resourceMembersAclPermission,
					permissionOrigin: [
						"type" => "direct_member",
					],
				);

				$nextInheritedMemberPrincipals[] = $this->addInheritanceOriginToPrincipal($memberPrincipal, $resource);
			}
		}

		// Manager Permissions
		foreach($resourceManagers as $resourceManager) {
			$memberPrincipal = $resourceManager->getPrincipal();
			
			if($resourceManagersAclPermission > 0) {
				$permissionsList->addPermission(
					principal: $memberPrincipal,
					permissions: $resourceManagersAclPermission,
					permissionOrigin: [
						"type" => "direct_manager",
					],
				);

				// NOTE: Managers will get added to both nextInheritedMemberPrincipals and nextInheritedManagerPrincipals,
				// because even if manager inheritance is disabled in a child resource if they have read permissions they qualify for resourceInheritedAclPermission permissions
				$nextInheritedMemberPrincipals[] = $this->addInheritanceOriginToPrincipal($memberPrincipal, $resource);
			}

			$nextInheritedManagerPrincipals[] = $this->addInheritanceOriginToPrincipal($memberPrincipal, $resource);
		}

		return [
			"permissionsList" => $permissionsList,
			"nextInheritedMemberPrincipals" => $nextInheritedMemberPrincipals,
			"nextInheritedManagerPrincipals" => $nextInheritedManagerPrincipals,
			"nextImplicitlyDeactivated" => (!$resource->getActive() || $implicitlyDeactivated),
		];
	}

	/**
	 * @param Resource $resource
	 * @param bool $enableOriginTracing
	 * @return \Generator<mixed, ResourcePermissionsList, mixed, void>
	 */
	public function generateResourcePermissionsListsAlongPathToResource(Resource $resource, bool $enableOriginTracing = false) {
		$resourcePath = $this->resourceService->getAllResourcesOnPathFromRootToResource($resource);

		$organizationFolder = $this->organizationFolderService->find($resource->getOrganizationFolderId());

		[$organizationFolderMemberPrincipals, $organizationFolderManagerPrincipals] = $this->organizationFolderService->getMemberAndManagerPrincipals($organizationFolder);

		$inheritedMemberPrincipals = $this->addInheritanceOriginToPrincipals($organizationFolderMemberPrincipals, $organizationFolder);
		$inheritedManagerPrincipals = $this->addInheritanceOriginToPrincipals($organizationFolderManagerPrincipals, $organizationFolder);

		$implicitlyDeactivated = false;

		foreach($resourcePath as $resourceOnPath) {
			$resourceMembers = $this->resourceMemberService->findAll($resourceOnPath->getId(), [
				"permissionLevel" => ResourceMemberPermissionLevel::MEMBER,
			]);
			$resourceManagers = $this->resourceMemberService->findAll($resourceOnPath->getId(), [
				"permissionLevel" => ResourceMemberPermissionLevel::MANAGER,
			]);

			[
				"permissionsList" => $permissionsList,
				"nextInheritedMemberPrincipals" => $inheritedMemberPrincipals,
				"nextInheritedManagerPrincipals" => $inheritedManagerPrincipals,
				"nextImplicitlyDeactivated" => $implicitlyDeactivated,
			] = $this->calculateResourcePermissionsListAndFurtherInheritedPrincipals(
				resource: $resourceOnPath,
				inheritedMemberPrincipals: $inheritedMemberPrincipals,
				inheritedManagerPrincipals: $inheritedManagerPrincipals,
				resourceMembers: $resourceMembers,
				resourceManagers: $resourceManagers,
				implicitlyDeactivated: $implicitlyDeactivated,
				enableOriginTracing: $enableOriginTracing,
			);

			yield $permissionsList;
		}
	}

	/**
	 * @param Resource[] $resources
	 * @param string $path
	 * @param InheritedPrincipal[] $inheritedMemberPrincipals
	 * @param InheritedPrincipal[] $inheritedManagerPrincipals
	 * @param bool $implicitlyDeactivated
	 * @return \Generator<mixed, ResourcePermissionsList, mixed, void>
	 */
	private function generateResourcePermissionsListsRecursively(
		array $resources,
		string $path,
		array $inheritedMemberPrincipals,
		array $inheritedManagerPrincipals,
		bool $implicitlyDeactivated = false,
		bool $enableOriginTracing = false,
	) {
		foreach($resources as $resource) {
			$resourceMembers = $this->resourceMemberService->findAll($resource->getId(), [
				"permissionLevel" => ResourceMemberPermissionLevel::MEMBER,
			]);
			$resourceManagers = $this->resourceMemberService->findAll($resource->getId(), [
				"permissionLevel" => ResourceMemberPermissionLevel::MANAGER,
			]);

			[
				"permissionsList" => $permissionsList,
				"nextInheritedMemberPrincipals" => $nextInheritedMemberPrincipals,
				"nextInheritedManagerPrincipals" => $nextInheritedManagerPrincipals,
				"nextImplicitlyDeactivated" => $nextImplicitlyDeactivated,
			] = $this->calculateResourcePermissionsListAndFurtherInheritedPrincipals(
				resource: $resource,
				inheritedMemberPrincipals: $inheritedMemberPrincipals,
				inheritedManagerPrincipals: $inheritedManagerPrincipals,
				resourceMembers: $resourceMembers,
				resourceManagers: $resourceManagers,
				implicitlyDeactivated: $implicitlyDeactivated,
				enableOriginTracing: $enableOriginTracing,
			);

			yield $permissionsList;

			$subResources = $this->resourceService->getSubResources($resource);

			yield from $this->generateResourcePermissionsListsRecursively(
				resources: $subResources,
				path: $path . $resource->getName() . "/",
				inheritedMemberPrincipals: $nextInheritedMemberPrincipals,
				inheritedManagerPrincipals: $nextInheritedManagerPrincipals,
				implicitlyDeactivated: $nextImplicitlyDeactivated,
				enableOriginTracing: $enableOriginTracing,
			);
		}
	}

	/**
	 * Generator to assemble ResourcePermissionsLists for every resource in an organization folder.
	 * 
	 * If the caller already fetched the OrganizationFolder member and manager Principals (using getMemberAndManagerPrincipals)
	 * it can provide them, so they don't have to be fetched again.
	 * 
	 * @param OrganizationFolder $organizationFolder
	 * @psalm-param ?list<PrincipalBackedByGroup> $organizationFolderMemberPrincipals
	 * @psalm-param ?list<PrincipalBackedByGroup> $organizationFolderManagerPrincipals
	 */
	public function generateAllResourcePermissionListsInOrganizationFolder(
		OrganizationFolder $organizationFolder,
		?array $organizationFolderMemberPrincipals = null,
		?array $organizationFolderManagerPrincipals = null,
		bool $enableOriginTracing = false,
	) {
		$topLevelResources = $this->resourceService->findAll($organizationFolder->getId(), null);

		if(!(isset($organizationFolderMemberPrincipals) && isset($organizationFolderManagerPrincipals))) {
			[$organizationFolderMemberPrincipals, $organizationFolderManagerPrincipals] = $this->organizationFolderService->getMemberAndManagerPrincipals($organizationFolder);
		}

		$inheritedMemberPrincipals = $this->addInheritanceOriginToPrincipals($organizationFolderMemberPrincipals, $organizationFolder);
		$inheritedManagerPrincipals = $this->addInheritanceOriginToPrincipals($organizationFolderManagerPrincipals, $organizationFolder);

		return $this->generateResourcePermissionsListsRecursively(
			resources: $topLevelResources,
			path: "",
			inheritedMemberPrincipals: $inheritedMemberPrincipals,
			inheritedManagerPrincipals: $inheritedManagerPrincipals,
			enableOriginTracing: $enableOriginTracing,
		);
	}

	/**
	 * Applies Permissions for every resource in OrganizationFolder to underlying system (Groupfolder ACLs for folder resources).
	 * 
	 * If the caller already fetched the OrganizationFolder member and manager Principals (using getMemberAndManagerPrincipals)
	 * it can provide them, so they don't have to be fetched again.
	 * 
	 * @param OrganizationFolder $organizationFolder
	 * @psalm-param ?list<PrincipalBackedByGroup> $organizationFolderMemberPrincipals
	 * @psalm-param ?list<PrincipalBackedByGroup> $organizationFolderManagerPrincipals
	 */
	public function applyAllResourcePermissionsInOrganizationFolder(
		OrganizationFolder $organizationFolder,
		?array $organizationFolderMemberPrincipals = null,
		?array $organizationFolderManagerPrincipals = null,
	): void {
		$permissionsListsGenerator = $this->generateAllResourcePermissionListsInOrganizationFolder(
			organizationFolder: $organizationFolder,
			organizationFolderMemberPrincipals: $organizationFolderMemberPrincipals,
			organizationFolderManagerPrincipals: $organizationFolderManagerPrincipals,
		);
		foreach($permissionsListsGenerator as $permissionsList) {
			// TODO: currently unique for only folder resources, needs to be generalized
			$this->aclManager->overwriteACLs($permissionsList->toGroupfolderAclList());
		}
    }
}