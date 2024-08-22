<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\OrganizationProvider;

abstract class OrganizationProvider {
	protected $id;

	public function getId() {
		return $this->id;
	}

	/* Return one level of the Organization Tree */
   /*
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
   *    │Organization 1│      │Organization 2│    ◄── ── ── getOrganizations();      
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
   *                   │ Suborg. 21 │ │ Suborg. 22 │   ◄── ── ── getOrganizations(2);
   *                 │ │            │ │            │ │                               
   *                   └────────────┘ └────────────┘                                 
   *                 └── ── ── ── ── ── ── ── ── ── ─┘                               
   */
	abstract public function getOrganizations(?int $parentOrganizationId);

	abstract public function getRolesOfOrganization(int $organizationId);
}