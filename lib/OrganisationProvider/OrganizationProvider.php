<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\OrganizationProvider;

use OCA\OrganizationFolders\Model\Organization;
use OCA\OrganizationFolders\Model\OrganizationRole;

abstract class OrganizationProvider {
	protected $id;

	public function getId() {
		return $this->id;
	}

   /**
    * Get specific role by its id (unique within OrganizationProvider)
    * @return Organization
    */
	abstract public function getOrganization(int $id): Organization;

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
	abstract public function getSubOrganizations(?int $parentOrganizationId): array;

   /**
    * Get specific role by its id (unique within OrganizationProvider)
    *
    * @return OrganizationRole
    */
    abstract public function getRole(int $id): OrganizationRole;

   /**
    * Get all roles of a specific organization
    * 
    * @return OrganizationRole[]
    */
	abstract public function getRolesOfOrganization(int $organizationId): array;

}