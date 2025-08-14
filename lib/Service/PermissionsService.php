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
use OCA\OrganizationFolders\Model\ResourcePermissions\ResourcePermissionsList;
use OCA\OrganizationFolders\Model\ResourcePermissions\ResourcePermissionsListWithOriginTracing;
use OCA\OrganizationFolders\Model\ResourcePermissions\ResourcePermissionsApplyPlanFactory;
use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Enum\PermissionOriginType;
use OCA\OrganizationFolders\Errors\Api\WouldCauseTooManyPermissionsChanges;

class PermissionsService {
	public function __construct(
		protected readonly OrganizationFolderService $organizationFolderService,
		protected readonly OrganizationFolderMemberService $organizationFolderMemberService,
		protected readonly ResourceService $resourceService,
		protected readonly ResourceMemberService $resourceMemberService,
		protected readonly ResourcePermissionsApplyPlanFactory $resourcePermissionsApplyPlanFactory,
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
		// TODO: No longer add 0 permissions, skip those principals instead, it's the system belows responsibility to add a default-deny, so these are no longer needed/wanted

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
				permissionsBitmap: $inheritedMemberPermissions,
				permissionOriginType: PermissionOriginType::INHERITED_MEMBER,
				permissionInheritedFrom: $inheritedMemberPrincipal->getOrigin(),
			);
		}

		// Inherited Manager Permissions
		foreach($inheritedManagerPrincipals as $inheritedManagerPrincipal) {
			$permissionsList->addPermission(
				principal: $inheritedManagerPrincipal->getPrincipal(),
				permissionsBitmap: $inheritedManagerPermissions,
				permissionOriginType: PermissionOriginType::INHERITED_MANAGER,
				permissionInheritedFrom: $inheritedManagerPrincipal->getOrigin(),
			);
		}

		// Member Permissions
		if($resourceMembersAclPermission > 0) {
			foreach($resourceMembers as $resourceMember) {
				$memberPrincipal = $resourceMember->getPrincipal();

				$permissionsList->addPermission(
					principal: $memberPrincipal,
					permissionsBitmap: $resourceMembersAclPermission,
					permissionOriginType: PermissionOriginType::MEMBER,
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
					permissionsBitmap: $resourceManagersAclPermission,
					permissionOriginType: PermissionOriginType::MANAGER,
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
	 * @param int $organizationFolderId
	 * @psalm-return array{0: InheritedPrincipal[], 1: InheritedPrincipal[]}
	 */
	private function getOrganizationFolderMemberInheritedPrincipalsById(int $organizationFolderId) {
		$organizationFolder = $this->organizationFolderService->find($organizationFolderId);

		return $this->getOrganizationFolderMemberInheritedPrincipals($organizationFolder);
	}

	/**
	 * @param OrganizationFolder $organizationFolder
	 * @psalm-return array{0: InheritedPrincipal[], 1: InheritedPrincipal[]}
	 */
	private function getOrganizationFolderMemberInheritedPrincipals(OrganizationFolder $organizationFolder) {
		[$organizationFolderMemberPrincipals, $organizationFolderManagerPrincipals] = $this->organizationFolderService->getMemberAndManagerPrincipals($organizationFolder);

		$inheritedMemberPrincipals = $this->addInheritanceOriginToPrincipals($organizationFolderMemberPrincipals, $organizationFolder);
		$inheritedManagerPrincipals = $this->addInheritanceOriginToPrincipals($organizationFolderManagerPrincipals, $organizationFolder);

		return [$inheritedMemberPrincipals, $inheritedManagerPrincipals];
	}

	/**
	 * @param Resource $resource
	 * @param bool $enableOriginTracing
	 * @return \Generator<mixed, ResourcePermissionsList, mixed, void>
	 */
	public function generateResourcePermissionsListsAlongPathToResource(Resource $resource, bool $enableOriginTracing = false) {
		$resourcePath = $this->resourceService->getAllResourcesOnPathFromRootToResource($resource);

		[$inheritedMemberPrincipals, $inheritedManagerPrincipals] = $this->getOrganizationFolderMemberInheritedPrincipalsById($resource->getOrganizationFolderId());

		$implicitlyDeactivated = false;

		foreach($resourcePath as $resourceOnPath) {
			[$resourceMembers, $resourceManagers] = $this->resourceMemberService->findAllByPermissionLevel($resourceOnPath->getId());

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
	 * Warning to callers of this function: Do not use the Generator indices (they reset to 0 when beginning with the subresources)
	 * 
	 * @param Resource $resource
	 * @param bool $enableOriginTracing
	 * @return \Generator<mixed, ResourcePermissionsList, mixed, void>
	 */
	public function generateResourcePermissionsListsAlongPathToAndSubresourcesOfResource(Resource $resource, bool $enableOriginTracing = false) {
		$resourcePath = $this->resourceService->getAllResourcesOnPathFromRootToResource($resource, false);

		[$inheritedMemberPrincipals, $inheritedManagerPrincipals] = $this->getOrganizationFolderMemberInheritedPrincipalsById($resource->getOrganizationFolderId());

		$implicitlyDeactivated = false;

		foreach($resourcePath as $resourceOnPath) {
			[$resourceMembers, $resourceManagers] = $this->resourceMemberService->findAllByPermissionLevel($resourceOnPath->getId());

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

		yield from $this->generateResourcePermissionsListsRecursively(
			resources: [$resource],
			inheritedMemberPrincipals: $inheritedMemberPrincipals,
			inheritedManagerPrincipals: $inheritedManagerPrincipals,
			implicitlyDeactivated: $implicitlyDeactivated,
			enableOriginTracing: $enableOriginTracing,
		);
	}

	/**
	 * @param Resource[] $resources
	 * @param InheritedPrincipal[] $inheritedMemberPrincipals
	 * @param InheritedPrincipal[] $inheritedManagerPrincipals
	 * @param bool $implicitlyDeactivated
	 * @return \Generator<mixed, ResourcePermissionsList, mixed, void>
	 */
	private function generateResourcePermissionsListsRecursively(
		array $resources,
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
			$this->resourcePermissionsApplyPlanFactory->buildPlan($permissionsList)->apply();
		}
    }

	/**
	 * Applies all Permissions, that could have been changed by the update.
	 * The amount of changes in the updated resource can be limited with maxiumumPermissionsChanges.
	 * @param \OCA\OrganizationFolders\Db\Resource $updatedResource
	 * @param mixed $maxiumumPermissionsChanges Throw when the permissions of more than this number of users were added or delted to the updated resource (changes to existing permissions are not counted) (changes to other resources are not counted)
	 * @return void
	 */
	public function applyResourcePermissionsAfterResourceUpdate(
		Resource $updatedResource,
		?int $maxiumumUsersPermissionsAddedOrDeleted = null,
	) {
		/** @var ResourcePermissionsList[] */
		$permissionsLists = iterator_to_array($this->generateResourcePermissionsListsAlongPathToAndSubresourcesOfResource($updatedResource), false);
		
		if(isset($maxiumumUsersPermissionsAddedOrDeleted)) {
			// find permissionsList of updatedResource itself to check if rollback is needed before applying any permissions
			foreach($permissionsLists as $permissionsList) {
				if($permissionsList->getResource()->getId() === $updatedResource->getId()) {
					$updatePlan = $this->resourcePermissionsApplyPlanFactory->buildPlan($permissionsList);

					$additions = $updatePlan->getNumberOfUsersWithPermissionsPotentiallyAdded();
					$deletions = $updatePlan->getNumberOfUsersWithPermissionsPotentiallyDeleted();

					if(($additions + $deletions) > $maxiumumUsersPermissionsAddedOrDeleted) {
						throw new WouldCauseTooManyPermissionsChanges($additions, $deletions);
					}

					break;
				}
			}
		}

		foreach($permissionsLists as $permissionsList) {
			$this->resourcePermissionsApplyPlanFactory->buildPlan($permissionsList)->apply();
		}
	}
}