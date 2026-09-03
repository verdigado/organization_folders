<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\OrganizationProvider;

use OCA\OrganizationFolders\Model\Organization;
use OCA\OrganizationFolders\Model\OrganizationRole;

use OCA\OrganizationFolders\Public\Errors\OrganizationNotFound;
use OCA\OrganizationFolders\Public\Errors\OrganizationRoleNotFound;

abstract class OrganizationProvider {
	protected $id;

	public function getId(): string {
		return $this->id;
	}

    public function getFriendlyName(): string {
		return $this->getId();
	}

	/**
	 * Get specific organization by its id (unique within OrganizationProvider)
	 * TODO: Rename to getOrganizationById()
	 * @return Organization
	 * @throws OrganizationNotFound
	 */
	abstract public function getOrganization(int $id): Organization;

	/**
	 * In most providers this will contain zero or one elements,
	 * but memberGroupId uniqueness across the organizations is not strictly neccessary
	 * @param string $groupId
	 * @return Organization[]
	 */
	abstract public function getOrganizationsByMembersGroupId(string $groupId): array;

	/**
	 * Return one level of the Organization Tree
	 *
	 *        ┌────────────────────────────┐                                           
	 *        │         Root Node          │                                           
	 *        │ (of Organization Provider) │                                           
	 *        └──┬──────────────────────┬──┘                                           
	 *           │                      │                                              
	 *           │                      │                                              
	 * ┌── ── ── │── ── ── ── ── ── ── ─│─ ── ── ─┐                                    
	 *           │                      │                                              
	 * │         ▼                      ▼         │                                    
	 *    ┌──────────────┐      ┌──────────────┐                                       
	 * │  │              │      │              │  │                                    
	 *    │Organization 1│      │Organization 2│    ◄── ── ── getSubOrganizations();      
	 * │  │              │      │              │  │                                    
	 *    └┬────────────┬┘      └┬────────────┬┘                                       
	 * │   │            │        │            │   │                                    
	 *     │            │        │            │                                        
	 * └── ├─ ── ── ── ─┤ ── ── ─┼ ── ── ── ──│── ┘                                    
	 *     │            │        │            │                                        
	 *     ▼            ▼        │            │                                        
	 *    ...          ...       ▼            ▼                                        
	 *                 ┌── ── ── ── ── ── ── ── ── ── ─┐                               
	 *                   ┌────────────┐ ┌────────────┐                                 
	 *                 │ │            │ │            │ │                               
	 *                   │ Suborg. 21 │ │ Suborg. 22 │   ◄── ── ── getSubOrganizations(2);
	 *                 │ │            │ │            │ │                               
	 *                   └────────────┘ └────────────┘                                 
	 *                 └── ── ── ── ── ── ── ── ── ── ─┘                               
	 *
	 * @return Organization[]
	 */
	abstract public function getSubOrganizations(?int $parentOrganizationId = null): array;

	/**
	 * Get a specific role by its id (must be unique within organization provider, not just within parent organization)
	 *
	 * @return OrganizationRole
	 * @throws OrganizationRoleNotFound
	 */
	abstract public function getRole(string $id): OrganizationRole;

	/**
	 * In most providers this will contain zero or one elements,
	 * but memberGroupId uniqueness across the roles is not strictly neccessary
	 * @param string $groupId
	 * @return OrganizationRole[]
	 */
	abstract public function getRolesByMembersGroupId(string $groupId): array;

	/**
	 * Get all roles of a specific organization
	 * 
	 * @return OrganizationRole[]
	 */
	abstract public function getRolesOfOrganization(int $organizationId): array;

}