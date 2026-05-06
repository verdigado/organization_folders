<?php

namespace OCA\OrganizationFolders\Command\OrganizationFolder;

use OCP\DB\Exception;
use OC\Core\Command\InterruptedException;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Model\ResourcePermissions\ResourcePermissionsApplyPlan;

class FixACLsOfOrganizationFolder extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:recreate-acls')
			->setDescription('Ensures all ACLs of organization folder are correctly set. Should not be neccessary to run unless ACL rules have been modified accidentally by an admin as ACLs will be created/modified automatically as resources are changed')
			
			->addArgument('id', InputArgument::OPTIONAL, 'Id of the organization folder')
			->addOption('all', null, InputOption::VALUE_NONE, 'Run for all organization folders instead of specific id')

			->addUsage('1')
			->addUsage('1 -vvv')
			->addUsage('--all');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$all = $input->getOption('all');
		$id = $input->getArgument('id');
		
		try {
			if($all) {
				$organizationFolders = $this->organizationFolderService->findAll();

				foreach($organizationFolders as $organizationFolder) {
					$this->processOrganizationFolder($organizationFolder, $output);

					try {
						$this->abortIfInterrupted();
					} catch (InterruptedException) {
						$output->writeln('<info>User Interupt, stopping</info>');
						break;
					}
				}

				return 0;
			} else {
				if(isset($id)) {
					$id = (int)$id;
					$organizationFolder = $this->organizationFolderService->find($id);

					$this->processOrganizationFolder($organizationFolder, $output);

					return 0;
				} else {
					$output->writeln("Error: Must provide id or --all");
					return 1;
				}
			}
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}

	private function processOrganizationFolder(OrganizationFolder $organizationFolder, OutputInterface $output) {
		$output->write("Applying permissions for organization folder \"" . $organizationFolder->getName() . "\" (ID: " . $organizationFolder->getId() . ")...");

		$changes = 0;

		[$memberPrincipals, $managerPrincipals] = $this->organizationFolderService->getMemberAndManagerPrincipals($organizationFolder);
		

		// Step 1: Refresh groupfolder members
		$groupfolderMemberChanges = $this->organizationFolderService->refreshGroupfolderMembers($organizationFolder, $memberPrincipals, $managerPrincipals);

		$numGroupfolderMembersCreated = count($groupfolderMemberChanges['created']);
		$numGroupfolderMembersUpdated = count($groupfolderMemberChanges['updated']);
		$numGroupfolderMembersRemoved = count($groupfolderMemberChanges['removed']);

		$changes += $numGroupfolderMembersCreated;
		$changes += $numGroupfolderMembersUpdated;
		$changes += $numGroupfolderMembersRemoved;
		

		// Step 2: Refresh resource permissions
		switch($output->getVerbosity()) {
			case OutputInterface::VERBOSITY_VERBOSE:
				$output->writeln("");
				$output->writeln("  Refreshed groupfolder member groups:");
				$output->writeln("    Changes: " . $this->printNumberOfChanges($numGroupfolderMembersCreated, $numGroupfolderMembersUpdated, $numGroupfolderMembersRemoved));
				$progress = function(ResourcePermissionsApplyPlan $plan) use ($output) {
					$resource = $plan->getResource();
					$output->writeln("  Applied permissions in resource \"" . $resource->getName() . "\" (ID: " . $resource->getId() . "):");
					$output->writeln("    Changes: " . $this->printNumberOfChanges($plan->getNumberOfAdditions(), $plan->getNumberOfUpdates(), $plan->getNumberOfDeletions()));
					$output->writeln("    Effective permission changes: " . $this->printNumberOfChanges($plan->getNumberOfEffectivePermissionsAdditions(), $plan->getNumberOfEffectivePermissionsUpdates(), $plan->getNumberOfEffectivePermissionsDeletions()));
				};
				break;
			case OutputInterface::VERBOSITY_VERY_VERBOSE:
			case OutputInterface::VERBOSITY_DEBUG:
				$output->writeln("");
				$output->writeln("  Refreshed groupfolder member groups:");
				$output->writeln("    Changes: " . $this->printChanges($groupfolderMemberChanges['created'], $groupfolderMemberChanges['updated'], $groupfolderMemberChanges['removed']));
				$progress = function(ResourcePermissionsApplyPlan $plan) use ($output) {
					$resource = $plan->getResource();
					$output->writeln("  Applied permissions in resource \"" . $resource->getName() . "\" (ID: " . $resource->getId() . "):");
					$output->writeln("    Changes: " . $this->printChanges($plan->getAdditions(), $plan->getUpdates(), $plan->getDeletions()));
					$output->writeln("    Effective permission changes: " . $this->printNumberOfChanges($plan->getNumberOfEffectivePermissionsAdditions(), $plan->getNumberOfEffectivePermissionsUpdates(), $plan->getNumberOfEffectivePermissionsDeletions()));
					$output->writeln("    Users permissions affected: up to " . $this->printNumberOfChanges($plan->getNumberOfUsersWithPermissionsPotentiallyAdded(), $plan->getNumberOfUsersWithPermissionsPotentiallyChanged(), $plan->getNumberOfUsersWithPermissionsPotentiallyDeleted()));
				};
				break;
			default:
				$progress = null;
				break;
		}

		$changes += $this->permissionsService->applyAllResourcePermissionsInOrganizationFolder($organizationFolder, $memberPrincipals, $managerPrincipals, $progress);

		$output->writeln("  done (" . $changes . " changes made)");
	}

	private function printChanges(array $created, array $updated, array $removed): string {
		$numCreated = count($created);
		$numUpdated = count($updated);
		$numRemoved = count($removed);

		$sum = $numCreated + $numUpdated + $numRemoved;
		
		if($sum > 0) {
			$details = [];

			if($numCreated > 0) {
				$details[] = json_encode($created) . " added";
			}

			if($numUpdated > 0) {
				$details[] = json_encode($updated) . " updated";
			}

			if($numRemoved > 0) {
				$details[] = json_encode($removed) . " deleted";
			}

			return $sum . " (" . implode(", ", $details) . ")";
		} else {
			return "0";
		}
	}

	private function printNumberOfChanges(int $numCreated, int $numUpdated, int $numRemoved): string {
		$sum = $numCreated + $numUpdated + $numRemoved;

		if($sum > 0) {
			$details = [];

			if($numCreated > 0) {
				$details[] = $numCreated . " added";
			}

			if($numUpdated > 0) {
				$details[] = $numUpdated . " updated";
			}

			if($numRemoved > 0) {
				$details[] = $numRemoved . " deleted";
			}

			return $sum . " (" . implode(", ", $details) . ")";
		} else {
			return "0";
		}
	}
}
